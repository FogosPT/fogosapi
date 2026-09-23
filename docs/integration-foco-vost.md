# Integração: FOCO (foco.vost.pt) → FogosPT API

FOCO — *Fire Observation and Characterisation Observatory*, da VOST Portugal — expõe dois produtos por incêndio activo, derivados do satélite MTG (Meteosat Third Generation) da EUMETSAT:

1. **Perímetro actual** — polígono da área ardida estimada a partir da agregação temporal de detecções FRP (Fire Radiative Power) do produto MTG MTFRPPixel do LSA SAF (alojado no IPMA).
2. **Simulação de propagação a 6 horas** — isócronas +1h a +6h calculadas pelo **PyroCast** (motor da VOST) usando taxa de propagação e humidade de combustível, COS2023 (cobertura do solo / carga de combustível), Copernicus GLO-90 DEM (relevo) e CAOP 2025 (fronteiras administrativas).

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
| `GET`  | `/api/external/v1/events` | Lista de perímetros activos (GeoJSON `FeatureCollection`). Query opcional `hours=N` (default 24; aumentado dinamicamente para cobrir o incêndio activo mais antigo). |
| `POST` | `/api/external/v1/simulate` | Simulação de propagação para um incidente. Body: `{ fogos_id, hours: 6, weather_source: "ipma" }`. Resposta: `FeatureCollection` com isócronas +1h..+6h e `properties.wind` / `properties.fogos_url`. |

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

### 2.1. `ProcessMTGPerimeters` — de 15 em 15 minutos

Ficheiro: `app/Jobs/ProcessMTGPerimeters.php`.

Fluxo:
1. Descobre o incêndio activo mais antigo (`Incident::isActive()->isFire()->min('dateTime')`) e calcula `hours = max(24, diff_horas)`. Isto garante que um incêndio de 3 dias continua a aparecer no snapshot mesmo quando o `hours` default (24h) já não o cobre.
2. `GET /api/external/v1/events?hours={hoursParam}`.
3. Valida que o payload é um `FeatureCollection`. Se não for, **não** invalida a cache — preserva o último snapshot bom (resiliência a falhas transitórias do upstream).
4. Para cada `feature`:
   - Se tem `properties.fogospt_incident.id`, arquiva uma linha em **`fire_perimeters_history`** (Mongo) com o feature completo, `fetched_at`, contadores (`detections`, `total_frp_mw`, `area_km2`) e o `source_cluster_id`.
   - Features sem correlação a incidente são preservadas no snapshot cacheado mas não vão para o histórico.
5. Escreve o corpo bruto em Redis:
   - `mtg:perimeters` → JSON bruto (TTL 1h)
   - `mtg:perimeters:fetched_at` → ISO 8601 do último poll (TTL 1h)

Timeout do job: 300s. Timeout HTTP: 20s conexão 5s.

### 2.2. `ProcessMTGSimulation` — de hora a hora

Ficheiro: `app/Jobs/ProcessMTGSimulation.php`.

Fluxo:
1. Se não há incêndios activos → sai.
2. Para cada `Incident::isActive()->isFire()`:
   - `POST /api/external/v1/simulate` com `{fogos_id, hours: 6, weather_source: "ipma"}`.
   - Valida GeoJSON.
   - Arquiva em **`fire_simulations_history`** (Mongo): `feature_collection` completo, `wind`, `hours`, `fogos_url`, `payload_hash = sha1(body)`, `fetched_at`.
3. Após o loop, escreve **a última** simulação bem-sucedida em Redis:
   - `mtg:simulation` → corpo bruto (TTL 2h)
   - `mtg:simulation:fetched_at` → ISO 8601 (TTL 2h)

Nota: apenas **uma** simulação vive no Redis global — foi decisão intencional para o dashboard global mostrar sempre o incêndio mais recentemente simulado. Consumidores que precisem da simulação de um incêndio específico usam o endpoint per-incident (§3), que lê o histórico Mongo em vez do Redis global.

Timeout do job: 1200s (simulações são pesadas). Timeout HTTP por-incidente: 60s. Um incidente que falhe não interrompe o loop; segue para o próximo.

**Rate limit upstream: 10 req/min** em `/api/external/v1/simulate`. Para não bater no limite quando há muitos incêndios activos, o loop faz `sleep(7)` entre pedidos (constante `SIMULATE_INTERVAL_SECONDS` no job) — ~8.5 req/min, com folga. Isto alonga o tempo total do job proporcionalmente ao número de incêndios (ex.: 20 incêndios ≈ 140s só de espera + tempo dos próprios pedidos), mas continua dentro do timeout de 1200s.

### 2.3. Registo no scheduler

`bootstrap/app.php`:

```php
$schedule->job(new ProcessMTGPerimeters())->everyFifteenMinutes();
$schedule->job(new ProcessMTGSimulation())->hourly();
```

Ambos só correm com `SCHEDULER_ENABLE=true`.

---

## 3. Endpoints públicos (v2)

Todos os endpoints devolvem **sempre HTTP 200** — quando não há dados devolvem `{ type: 'FeatureCollection', features: [] }` em vez de 404. Isto simplifica o cliente.

### 3.1. Snapshots globais

| Path | Descrição |
|---|---|
| `GET /v2/fire/perimeters` | Snapshot completo do último `ProcessMTGPerimeters` (todas as features, incluindo as não-correlacionadas). Resposta: `FeatureCollection` bruto do upstream, mais header `X-Fetched-At` com o ISO do último poll, e `Cache-Control: public, max-age=60`. Se o Redis estiver vazio (Redis reiniciado / ingestão nunca correu / TTL expirado), devolve `FeatureCollection` vazio. |
| `GET /v2/fire/simulation` | A **última** simulação bem-sucedida (uma só, para um só incêndio). Mesma resposta e headers. |

### 3.2. Per-incident

| Path | Descrição |
|---|---|
| `GET /v2/incidents/{id}/perimeter` | Última linha em `fire_perimeters_history` para aquele `incident_id`, embrulhada num `FeatureCollection` com uma única feature. Adiciona `fetched_at` ao objecto de topo. Cache Laravel 60s por incidente (`v2.fire.perimeter.incident.{id}`). |
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
| `mtg:perimeters` | Corpo JSON bruto do último `/events` bem-sucedido | 1h |
| `mtg:perimeters:fetched_at` | ISO 8601 do poll | 1h |
| `mtg:simulation` | Corpo JSON bruto da última simulação bem-sucedida | 2h |
| `mtg:simulation:fetched_at` | ISO 8601 do poll | 2h |
| `v2.fire.perimeter.incident.{id}` | Payload já formatado do endpoint per-incident (Cache facade) | 60s |
| `v2.fire.simulation.incident.{id}` | Idem para simulação | 60s |

### 4.2. Mongo

**`fire_perimeters_history`** (`App\Models\FirePerimeterHistory`):

| Campo | Tipo | Notas |
|---|---|---|
| `incident_id`       | string | ID fogos.pt (correlação vem do upstream) |
| `fetched_at`        | datetime | Quando o poll do upstream aconteceu |
| `feature`           | object | GeoJSON `Feature` completo |
| `source_cluster_id` | string | `properties.id` do cluster upstream |
| `detections`        | int    | número de detecções agregadas |
| `total_frp_mw`      | float  | soma FRP (MW) |
| `area_km2`          | float  | área do polígono |
| `created`/`updated` | datetime | timestamps Mongo |

**`fire_simulations_history`** (`App\Models\FireSimulationHistory`):

| Campo | Tipo | Notas |
|---|---|---|
| `incident_id`        | string | ID fogos.pt |
| `fetched_at`         | datetime | Quando a simulação foi feita |
| `feature_collection` | object | `FeatureCollection` GeoJSON completo (isócronas +1h..+6h) |
| `wind`               | object | `properties.wind` do upstream |
| `hours`              | int    | horizonte da simulação (sempre 6 actualmente) |
| `fogos_url`          | string | Link para a página fogos.pt do incidente (upstream) |
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
    │  ProcessMTGPerimeters    │  cada 15 min
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
| `MTG_FRP_PROCESSOR_ENABLE=false` | Jobs saem cedo. Endpoints devolvem o que estiver em Redis/Mongo. Após 1h/2h o Redis expira → snapshots globais devolvem `FeatureCollection` vazio; per-incident continua a servir da history até haver rows novas. |
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
