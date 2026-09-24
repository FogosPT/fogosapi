# Integração: FOCO (foco.vost.pt) → FogosPT API

FOCO — *Fire Observation and Characterisation Observatory*, da VOST Portugal — expõe dois produtos por incêndio activo, derivados do satélite MTG (Meteosat Third Generation) da EUMETSAT:

1. **Perímetro por evidência de satélite** — polígono derivado da agregação temporal de detecções FRP (Fire Radiative Power) do produto MTG MTFRPPixel do LSA SAF (alojado no IPMA). **Não é perímetro oficial ANEPC nem representa área ardida medida** — píxeis de 2-4 km, evidência de fogo activo.
2. **Simulação de propagação a 6 horas** — isócronas +1h a +6h calculadas pelo **PyroCast** (motor da VOST) usando taxa de propagação e humidade de combustível, COS2023 (cobertura do solo / carga de combustível), Copernicus GLO-90 DEM (relevo) e CAOP 2025 (fronteiras administrativas). É um cenário ("se nada parar o fogo") — **nunca previsão operacional**.

A FogosPT API não fala directamente com o LSA SAF nem processa MTG. Consome o **MTG FRP Processor** exposto pelo FOCO (`foco.vost.pt`) e limita-se a: ingerir → correlacionar com o dataset de incidentes ANEPC → cachear em Redis + arquivar histórico em Mongo → servir aos clientes via v2.

---

## 1. Endpoints upstream consumidos

Configurados via env, tratados como base URL + Bearer token:

```
MTG_FRP_PROCESSOR_ENABLE=true
MTG_FRP_PROCESSOR_URL=https://foco.vost.pt
MTG_FRP_PROCESSOR_TOKEN=<bearer>
```

Se qualquer um destes falhar (`ENABLE=false`, URL vazio, token vazio), os jobs saem cedo com log e a API continua a servir o último snapshot cacheado até este expirar.

| Método | Path (upstream) | Uso |
|---|---|---|
| `GET`  | `/api/external/v1/active` | **Fonte principal dos perímetros.** Um perímetro por incêndio fogos.pt activo, desde a ignição, com flood-fill de 4 km para isolar fogos vizinhos. Payload traz `features` + `errors` (por incidente sem coordenadas utilizáveis, etc.). Sem query params. |
| `GET`  | `/api/external/v1/events` | Camada secundária: clusters de detecções **não correlacionados** com nenhum incidente ANEPC (`fogospt_incident: null`). Servidos separadamente como "por confirmar" para não os esconder no mapa. |
| `POST` | `/api/external/v1/simulate` | Simulação de propagação para um evento. Requer `event_id` (obtido primeiro em `GET /events?fogos_id=<id>` — o `fogos_id` sozinho é só etiqueta). Body: `{ event_id, fogos_id, hours: 6, weather_source: "openmeteo", model: "v3", perturb: true }`. Resposta: `FeatureCollection` com isócronas +1h..+6h e (com `perturb`) `uncertainty_bands` + `burn_probability_bands`. |

Cabeçalhos comuns em cada request:

```
User-Agent: fogospt
Authorization: Bearer $MTG_FRP_PROCESSOR_TOKEN
Accept: application/json
```

`verify: false` no cliente Guzzle — o TLS do upstream por vezes é irregular; não vale a pena bloquear a ingestão por isso.

Se `PROXY_ENABLE=true`, todos os requests saem via `PROXY_URL`.

---

## 2. Jobs

Todos correm na queue `mtg-frp` (dedicated worker → um incêndio grande não bloqueia a ingestão ANEPC).

### 2.1. `ProcessMTGPerimeters` — de 10 em 10 minutos

Ficheiro: `app/Jobs/ProcessMTGPerimeters.php`.

Corre a cada 10 min porque o produto MTG tem slots de 10 min (publicados com 10-20 min de atraso). Cadências mais lentas geram aliasing (perde 2 em cada 3 slots).

Fluxo:
1. **Passo 1 — `/active`**: `GET /api/external/v1/active` (sem query params). Devolve um perímetro por incêndio activo, desde a ignição, com flood-fill de 4 km. Valida payload como `FeatureCollection`; se falhar, **não** invalida a cache — preserva o último snapshot bom.
2. Para cada `feature` com `properties.fogospt_incident.id` presente, arquiva em **`fire_perimeters_history`** (Mongo): `incident_id`, `fetched_at`, feature completo, `source_cluster_id` (`properties.id`), `detections`, `total_frp_mw`, `peak_frp_mw`, `first_seen`, `last_seen`, `area_km2`, `errors` (do payload upstream indexado por `fogospt_incident.id`).
3. Para incidentes que só aparecem em `payload.errors` (ex.: `"no usable coordinates"`) — sem `feature` associada — arquiva uma linha com `feature: null` + `errors: [...]`. O endpoint per-incident usa isto para mostrar "sem perímetro possível" com razão em vez de silêncio.
4. Escreve em Redis:
   - `mtg:perimeters` → JSON bruto do `/active` (TTL 24h)
   - `mtg:perimeters:fetched_at` → ISO 8601 do último poll (TTL 24h)
5. **Passo 2 — `/events` (não correlacionados)**: `GET /api/external/v1/events` (sem query). Filtra as features onde `properties.fogospt_incident` é `null` — clusters que o satélite viu mas que a ANEPC não abriu como ocorrência. **Não** são persistidos no histórico; ficam só em Redis:
   - `mtg:events_uncorrelated` → `FeatureCollection` só com as features não-correlacionadas (TTL 24h)
   - `mtg:events_uncorrelated:fetched_at` → ISO 8601 (TTL 24h)

Timeout do job: 300s. Timeout HTTP: 20s conexão 5s. Ambas as chamadas usam `http_errors: false` no Guzzle para permitir classificação por status (200 → OK, 4xx/5xx → warning + preservar cache).

### 2.2. `ProcessMTGSimulation` — de hora a hora

Ficheiro: `app/Jobs/ProcessMTGSimulation.php`.

Fluxo:
1. Se não há incêndios activos → sai.
2. Para cada `Incident::isActive()->isFire()`:
   - **Resolver `event_id`**: `GET /api/external/v1/events?fogos_id=<id>`. Se a resposta não tem features → skip com log DEBUG (satélite não vê o fogo). Se não tem `properties.id` no primeiro feature → skip com WARNING (contrato mudou). Caso contrário, ler `event_id`.
   - `POST /api/external/v1/simulate` com `{event_id, fogos_id, hours: 6, weather_source: "openmeteo", model: "v3", perturb: true}`. Por que estes valores:
     - `openmeteo` dá previsão horária no local (o `ipma` usa vento constante da estação mais próxima — inadequado para 6h).
     - `model: v3` é o motor completo (o default seria `v1` heurístico).
     - `perturb: true` traz `uncertainty_bands` + `burn_probability_bands` no resultado.
   - **Classificar resposta antes de guardar** (validar só o GeoJSON deixaria passar 4xx/5xx com corpo aparentemente-válido):
     - `200` → guardar em Mongo + actualizar cache global.
     - `422 out_of_coverage / fuel_nodata / invalid_fuel_codes` e `503 fuel_source_unavailable`, `501 engine_unavailable` → log **INFO** (característica do incidente/upstream, não erro nosso).
     - `429 Too Many Requests` → ler `Retry-After`, `sleep`, **retentar este incidente uma vez**. Se falhar de novo, skip.
     - outros `4xx`/`5xx` → log **WARNING**; caches globais **não** são invalidadas.
   - Arquiva em **`fire_simulations_history`** (Mongo): `feature_collection` completo, `wind`, `wind_mode`, `hours`, `fogos_url`, `fuel_source`, `ros_source`, `moisture_source`, `payload_hash = sha1(body)`, `fetched_at`.
3. Após o loop, escreve **a última** simulação bem-sucedida em Redis:
   - `mtg:simulation` → corpo bruto (TTL 24h)
   - `mtg:simulation:fetched_at` → ISO 8601 (TTL 24h)

Nota: apenas **uma** simulação vive no Redis global — foi decisão intencional para o dashboard global mostrar sempre o incêndio mais recentemente simulado. Consumidores que precisem da simulação de um incêndio específico usam o endpoint per-incident (§3), que lê o histórico Mongo em vez do Redis global.

Timeout do job: 1200s (simulações são pesadas). Timeout HTTP por-incidente: 60s. Um incidente que falhe não interrompe o loop; segue para o próximo.

**Rate limit upstream: 10 req/min** em `/api/external/v1/simulate`. O loop faz `sleep(7)` entre pedidos (constante `SIMULATE_INTERVAL_SECONDS`) — ~8.5 req/min, com folga. Se mesmo assim vier 429 (rajadas concorrentes com outros consumidores do FOCO), o handler dedicado honra `Retry-After` e retenta uma vez o mesmo incidente antes de saltar.

### 2.3. Registo no scheduler

`bootstrap/app.php`:

```php
$schedule->job(new ProcessMTGPerimeters())->everyTenMinutes();
$schedule->job(new ProcessMTGSimulation())->hourly();
```

Ambos só correm com `SCHEDULER_ENABLE=true`.

---

## 3. Endpoints públicos (v2)

Todos os endpoints devolvem **sempre HTTP 200** — quando não há dados devolvem `{ type: 'FeatureCollection', features: [] }` em vez de 404. Isto simplifica o cliente.

### 3.1. Snapshots globais

Todos injectam um `disclaimer` (top-level + em cada `feature.properties`) e o header `X-Stale: true` se o `X-Fetched-At` estiver a mais de 30 min do agora (o utilizador vê os dados, marcados como antigos).

| Path | Descrição |
|---|---|
| `GET /v2/fire/perimeters` | Snapshot do último `/active` (uma feature por incidente activo). Resposta: `FeatureCollection` do upstream + `disclaimer` + `X-Fetched-At` + `Cache-Control: public, max-age=60`. Se o Redis estiver vazio devolve `FeatureCollection` vazio. |
| `GET /v2/fire/perimeters/uncorrelated` | Detecções MTG sem incidente ANEPC associado ("por confirmar"). Frontend deve pintar em camada distinta, nunca esconder. |
| `GET /v2/fire/simulation` | A **última** simulação bem-sucedida (uma só, para um só incêndio). Mesma resposta e headers, com `disclaimer` a explicar que é cenário e não previsão operacional. |

### 3.2. Per-incident

| Path | Descrição |
|---|---|
| `GET /v2/incidents/{id}/perimeter` | Última linha em `fire_perimeters_history` para aquele `incident_id`, embrulhada num `FeatureCollection` com uma única feature. Adiciona `fetched_at` + `disclaimer` ao objecto de topo. Se a última linha tem `feature: null` + `errors: [...]` (upstream indicou por exemplo `no usable coordinates`), devolve `features: []` e um campo `errors` explicativo — permite ao frontend mostrar "sem perímetro possível" com razão em vez de silêncio. Cache Laravel 60s por incidente (`v2.fire.perimeter.incident.{id}`). |
| `GET /v2/incidents/{id}/simulation` | Última linha em `fire_simulations_history` para aquele `incident_id`. Devolve o `feature_collection` completo com `fetched_at` injectado. Cache 60s. |

### 3.3. Correlação com incidentes fogos.pt

O upstream (FOCO) já anota cada feature com:

```json
"properties": {
  "id": "<cluster_id>",
  "fogospt_incident": {
    "id": "<fogos_id>",
    "url": "...",
    "concelho": "...",
    "freguesia": "...",
    "natureza": "...",
    "status": "..."
  },
  "detections": 42,
  "total_frp_mw": 187.3,
  "area_km2": 3.4
}
```

O frontend filtra client-side por `fogospt_incident.id` no snapshot global, ou usa o endpoint per-incident se só precisa de um.

**Features sem `fogospt_incident.id`** (satélite detectou algo que a ANEPC ainda não abriu como ocorrência, ou correlação falhou) aparecem no snapshot global mas **nunca** na tabela de histórico e por consequência nunca em `GET /v2/incidents/{id}/perimeter`.

---

## 4. Modelo de dados

### 4.1. Redis

| Chave | Conteúdo | TTL |
|---|---|---|
| `mtg:perimeters` | Corpo JSON bruto do último `/active` bem-sucedido | 24h |
| `mtg:perimeters:fetched_at` | ISO 8601 do poll | 24h |
| `mtg:events_uncorrelated` | `FeatureCollection` só com features `fogospt_incident: null` do último `/events` | 24h |
| `mtg:events_uncorrelated:fetched_at` | ISO 8601 do poll | 24h |
| `mtg:simulation` | Corpo JSON bruto da última simulação bem-sucedida | 24h |
| `mtg:simulation:fetched_at` | ISO 8601 do poll | 24h |
| `v2.fire.perimeter.incident.{id}` | Payload já formatado do endpoint per-incident (Cache facade) | 60s |
| `v2.fire.simulation.incident.{id}` | Idem para simulação | 60s |

### 4.2. Mongo

**`fire_perimeters_history`** (`App\Models\FirePerimeterHistory`):

| Campo | Tipo | Notas |
|---|---|---|
| `incident_id`       | string | ID fogos.pt (correlação vem do upstream) |
| `fetched_at`        | datetime | Quando o poll do upstream aconteceu |
| `feature`           | object\|null | GeoJSON `Feature` completo; `null` se o incidente aparece só em `payload.errors` |
| `source_cluster_id` | string | `properties.id` do cluster upstream |
| `detections`        | int    | número de detecções agregadas |
| `total_frp_mw`      | float  | soma FRP (MW) |
| `peak_frp_mw`       | float  | FRP máximo (MW) — do `/active` |
| `first_seen`        | datetime | primeira detecção do evento (`/active`) |
| `last_seen`         | datetime | última detecção do evento (`/active`) |
| `area_km2`          | float  | área do polígono (0 se v1 não fornecer) |
| `errors`            | array\|null | erros específicos ao incidente do payload `/active` (ex.: `no usable coordinates`) |
| `created`/`updated` | datetime | timestamps Mongo |

**`fire_simulations_history`** (`App\Models\FireSimulationHistory`):

| Campo | Tipo | Notas |
|---|---|---|
| `incident_id`        | string | ID fogos.pt |
| `fetched_at`         | datetime | Quando a simulação foi feita |
| `feature_collection` | object | `FeatureCollection` GeoJSON completo (isócronas +1h..+6h, mais `uncertainty_bands` / `burn_probability_bands` com `perturb: true`) |
| `wind`               | object | `properties.wind` do upstream |
| `wind_mode`          | string | Modo de vento usado pelo motor (`constant`, `hourly`, etc.) |
| `hours`              | int    | horizonte da simulação (sempre 6 actualmente) |
| `fogos_url`          | string | Link para a página fogos.pt do incidente (upstream) |
| `fuel_source`        | object | Dataset/versão de combustível usada (ex.: `zafm-dw-2026 v1.0`) |
| `ros_source`         | string | Fonte da taxa de propagação (ROS) |
| `moisture_source`    | string | Fonte da humidade de combustível |
| `payload_hash`       | string | `sha1(body)` — útil para dedup / detecção de re-runs idênticos |
| `created`/`updated`  | datetime | timestamps Mongo |

Nenhuma das colecções tem TTL / pruning automático — mantêm o histórico completo. Se ficar volumoso, colapsar antigos por dia ou fazer TTL index é um trabalho futuro.

---

## 5. Fluxo end-to-end

```
    ┌──────────────────────────┐
    │ EUMETSAT MTG / LSA SAF   │  MTFRPPixel a cada 10 min
    └────────────┬─────────────┘
                 │
                 ▼
    ┌──────────────────────────┐
    │ FOCO — MTG FRP Processor │  clustering + correlação com fogos.pt
    │ (foco.vost.pt)           │  + PyroCast (simulação)
    └────────────┬─────────────┘
                 │  /api/external/v1/events        (perímetros)
                 │  /api/external/v1/simulate      (simulação por incidente)
                 ▼
    ┌──────────────────────────┐
    │ FogosPT API — jobs       │
    │  ProcessMTGPerimeters    │  cada 10 min
    │  ProcessMTGSimulation    │  cada 1 h
    └──────┬───────────────┬───┘
           │               │
           ▼               ▼
    ┌────────────┐   ┌──────────────────────┐
    │ Redis      │   │ Mongo                │
    │ snapshots  │   │ *_history            │
    │ (globais)  │   │ (per-incident)       │
    └──────┬─────┘   └──────────┬───────────┘
           │                    │
           ▼                    ▼
    ┌──────────────────────────────────────┐
    │ /v2/fire/perimeters                  │  ← Redis (snapshot global)
    │ /v2/fire/simulation                  │  ← Redis (última sim.)
    │ /v2/incidents/{id}/perimeter         │  ← Mongo (mais recente do id)
    │ /v2/incidents/{id}/simulation        │  ← Mongo (mais recente do id)
    └──────────────────────────────────────┘
```

---

## 6. Operação

### 6.1. Ligar/desligar

```bash
# .env
MTG_FRP_PROCESSOR_ENABLE=true
MTG_FRP_PROCESSOR_URL=https://foco.vost.pt
MTG_FRP_PROCESSOR_TOKEN=<bearer>
```

Alterações requerem reload do PHP-FPM (opcache `validate_timestamps=0` em prod). O scheduler apanha na próxima janela.

### 6.2. Forçar ingestão manual

```bash
php artisan mtg:sync
```

Corre os dois jobs (`ProcessMTGPerimeters` + `ProcessMTGSimulation`) **sincronamente**, bypassa a queue. Útil para testar credenciais ou popular a cache logo após deploy.

### 6.3. Worker da queue dedicada

Os jobs vão para a queue `mtg-frp` — o worker Redis precisa de a consumir:

```bash
php artisan queue:work redis --queue=mtg-frp,default --tries=1 --timeout=1500
```

`--timeout=1500` para dar folga aos 1200s da `ProcessMTGSimulation`.

### 6.4. Inspecção rápida

```bash
# Está a cachear?
redis-cli GET mtg:perimeters:fetched_at
redis-cli GET mtg:simulation:fetched_at

# Quantas rows históricas por incidente?
mongo> db.fire_perimeters_history.aggregate([{$group: {_id: "$incident_id", n: {$sum: 1}}}, {$sort: {n: -1}}, {$limit: 10}])
mongo> db.fire_simulations_history.aggregate([{$group: {_id: "$incident_id", n: {$sum: 1}}}, {$sort: {n: -1}}, {$limit: 10}])
```

---

## 7. Modos de falha e degradação

| Falha | Comportamento |
|---|---|
| `MTG_FRP_PROCESSOR_ENABLE=false` | Jobs saem cedo. Endpoints devolvem o que estiver em Redis/Mongo. Após 24h o Redis expira → snapshots globais devolvem `FeatureCollection` vazio; per-incident continua a servir da history até haver rows novas. |
| Upstream 5xx / timeout | Log de warning. **Não invalida** cache — o último snapshot bom continua a ser servido (essencial para não apagar o mapa em situações de crise em que a rede está lenta). |
| Upstream devolve payload não-GeoJSON | Log de warning. Igual: cache antiga preservada. |
| Upstream muda contrato (ex.: renomeia `fogospt_incident` → `fogos_incident`) | Ingestão continua mas os endpoints per-incident deixam de encher (falta o `incident_id`). Snapshot global continua a servir as features (o cliente que fazia filtro por `fogospt_incident.id` deixa de ver correlação). Alerta antecedente: monitorizar `count(FirePerimeterHistory::where('created', '>=', now()->subHour()))` — se cair a 0 durante várias runs há problema. |
| Token expirado | 401 do upstream → warning nos logs, sem alteração à cache. Igual ao 5xx. |

---

## 8. O que **não** está feito (e porquê)

- **Sem pruning das colecções history.** Vão crescer indefinidamente. Não é urgente porque o volume é baixo (uma linha por incêndio activo a cada 15 min ≈ dezenas de MB/ano). Se ficar problemático: TTL index em `fetched_at` (ex.: 90 dias) resolve.
- **Sem retry automático nos jobs.** `--tries=1` deliberado — os jobs correm em intervalos curtos, um insucesso é apanhado na próxima run. Retries agressivos gastariam quota do upstream em situações em que o problema é de rede.
- **Sem circuit breaker.** Se o upstream ficar down por horas, continuamos a bater. É aceitável porque os jobs saem rápido em caso de erro e nós preservamos as caches.
- **Correlação fica no upstream.** A FogosPT não tenta correlacionar features MTG a incidentes por lat/lng — confia no `fogospt_incident.id` que o FOCO já anexou. Se o FOCO não conseguir correlacionar, nós também não conseguimos.

---

## 9. Referências rápidas

- Jobs: `app/Jobs/ProcessMTGPerimeters.php`, `app/Jobs/ProcessMTGSimulation.php`
- Comando: `app/Console/Commands/SyncMTGFireData.php` (`mtg:sync`)
- Controller: `app/Http/Controllers/FirePerimetersController.php`
- Modelos: `app/Models/FirePerimeterHistory.php`, `app/Models/FireSimulationHistory.php`
- Rotas: `routes/web.php` (grupos `v2/fire` e `v2/incidents/{id}/…`)
- Env: `.env.example` (`MTG_FRP_PROCESSOR_*`)
- OpenAPI: `docs/fires/paths/v2-fire-perimeters.yaml`, `v2-fire-simulation.yaml`, `v2-incident-perimeter.yaml`, `v2-incident-simulation.yaml`
- Upstream (para agente): `https://foco.vost.pt` — pedir token ao VOST antes de qualquer teste em prod.
