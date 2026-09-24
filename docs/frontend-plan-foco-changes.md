# Plano frontend — mudanças aos endpoints FOCO/MTG (v2)

Este documento resume o que muda para quem consome os endpoints `/v2/fire/*` e `/v2/incidents/{id}/{perimeter,simulation}` depois dos commits `5ad9e19`, `1ef6a06`, `e76e0ef` (Setembro 2026).

**Contexto.** A revisão feita pelo agente do FOCO (`foco.vost.pt`) apontou 1 bloqueante (a simulação estava a devolver 400 em todas as chamadas — endpoint da FogosPT API respondia com `FeatureCollection` vazio) e 9 melhorias que já foram implementadas do lado da API. Uma parte destas mudanças é transparente; outras precisam de ajustes no frontend.

---

## TL;DR — o que fazer

| # | Tarefa | Impacto | Prioridade |
|---|---|---|---|
| 1 | Adicionar `GET /v2/fire/perimeters/uncorrelated` como camada separada no mapa | **BREAKING** — features não-correlacionadas já não vêm em `/v2/fire/perimeters` | 🔴 alta |
| 2 | Substituir copy hard-coded ("área ardida") pelo campo `disclaimer` que vem no payload | Correção pedida explicitamente pelo VOST | 🔴 alta |
| 3 | Renderizar isócronas de `/v2/fire/simulation` e `/v2/incidents/{id}/simulation` | Endpoints estavam sempre vazios; agora trazem dados | 🟠 média |
| 4 | Ler header `X-Stale: true` e mostrar "dados de há X min" em vez de esconder | Melhora UX em outages | 🟠 média |
| 5 | Tratar campo `errors` em `/v2/incidents/{id}/perimeter` | Antes era só `features: []` silencioso | 🟠 média |
| 6 | Alinhar cadência de poll com 10 min (era 15) | Sem custo, sincroniza com a nova ingestão | 🟢 baixa |
| 7 | (opcional) Visualizar `uncertainty_bands` / `burn_probability_bands` na simulação | Só existe quando `perturb: true` no upstream — activo por defeito | 🟢 baixa |

---

## 1. Breaking — features não-correlacionadas mudaram de endpoint 🔴

### Antes
`GET /v2/fire/perimeters` devolvia **todas** as features do MTG, misturando as que têm `properties.fogospt_incident.id` (correlacionadas com um incidente ANEPC) e as que têm `properties.fogospt_incident: null` (satélite viu fogo mas a ANEPC ainda não abriu ocorrência, ou correlação falhou).

O frontend filtrava client-side, normalmente por `fogospt_incident.id`.

### Agora
`GET /v2/fire/perimeters` só traz as **correlacionadas** — um perímetro por incidente ANEPC activo, desde a ignição, com flood-fill de 4 km a impedir que fogos vizinhos se fundam num só polígono. Vem do endpoint upstream `/api/external/v1/active`.

As não-correlacionadas mudaram para:

```
GET /v2/fire/perimeters/uncorrelated
```

Payload igual (`FeatureCollection`), mas todas as features têm `properties.fogospt_incident: null`. Sugestão de rendering: camada distinta (linha tracejada, cor diferente, etiqueta "por confirmar"). **Nunca esconder** — são sinais precoces relevantes para protecção civil.

### O que fazer
1. Adicionar chamada nova a `/v2/fire/perimeters/uncorrelated` (mesmo intervalo do poll principal).
2. Renderizar num layer separado com estilo distinto.
3. Popup / tooltip deve reflectir que **não** há incidente ANEPC associado.
4. Remover qualquer filtro client-side por `fogospt_incident: null` sobre `/v2/fire/perimeters` — deixa de encontrar nada.

### Exemplo de payload

```json
{
  "type": "FeatureCollection",
  "disclaimer": "Detecção MTG sem incidente ANEPC associado (por confirmar).",
  "features": [
    {
      "type": "Feature",
      "geometry": { "type": "Polygon", "coordinates": [ /* ... */ ] },
      "properties": {
        "id": "cluster-abc-123",
        "fogospt_incident": null,
        "detections": 4,
        "total_frp_mw": 12.3,
        "disclaimer": "Detecção MTG sem incidente ANEPC associado (por confirmar)."
      }
    }
  ]
}
```

Headers: `X-Fetched-At: <ISO8601>`, `Cache-Control: public, max-age=60`, e opcionalmente `X-Stale: true`.

---

## 2. Copy fixo → usar `disclaimer` server-side 🔴

O VOST pediu explicitamente esta correcção — a terminologia anterior era imprecisa e podia induzir em erro em contexto de crise.

### Regra
Qualquer sítio do UI que diga **"área ardida"**, **"perímetro do incêndio"**, **"área queimada estimada"** ou variantes tem de ser substituído pelo texto que vem no campo `disclaimer` do payload. O texto está em PT e vem pronto para exibir.

### Onde vem o `disclaimer`
Cada endpoint devolve o texto certo para o seu contexto:

| Endpoint | Texto (top-level `disclaimer`) |
|---|---|
| `GET /v2/fire/perimeters` | *"Evidência de satélite MTG (LSA SAF/EUMETSAT). Não substitui perímetro oficial da ANEPC nem representa área ardida medida — píxeis de 2-4 km."* |
| `GET /v2/fire/perimeters/uncorrelated` | *"Detecção MTG sem incidente ANEPC associado (por confirmar)."* |
| `GET /v2/fire/simulation` | *"Cenário de propagação PyroCast baseado em vento e combustível. Não é previsão operacional — informação complementar, nunca alternativa à ANEPC."* |
| `GET /v2/incidents/{id}/perimeter` | igual ao perimeters global |
| `GET /v2/incidents/{id}/simulation` | igual ao simulation global |

O mesmo texto também está injectado em `feature.properties.disclaimer` — usar o que for mais conveniente (top-level para um footer/aviso do mapa; per-feature para popups).

### Onde colocar no UI
- Legenda do mapa: mostrar o `disclaimer` do endpoint correspondente ao layer.
- Popup / tooltip sobre um perímetro: repetir o `properties.disclaimer` da feature.
- Modal "sobre estes dados": copiar o texto completo dos 3 endpoints.
- Não esconder — o texto é **parte** da informação, não meta-informação opcional.

---

## 3. Simulação passa a ter dados 🟠

### Contexto
`/v2/fire/simulation` e `/v2/incidents/{id}/simulation` estavam **sempre** a devolver `{type: "FeatureCollection", features: []}` desde que o job existe, porque a API estava a fazer o POST ao upstream com um body malformado (o endpoint upstream exige `event_id`, não `fogos_id`). Isso foi corrigido no commit `5ad9e19`.

### O que muda no payload
Igual em estrutura ao que já estava documentado — mas agora com **features de facto**.

```json
{
  "type": "FeatureCollection",
  "fetched_at": "2026-09-24T14:32:11+00:00",
  "disclaimer": "Cenário de propagação PyroCast...",
  "properties": {
    "fogos_id": "2026123456789",
    "wind": { "speed": 18.5, "direction": 210 },
    "wind_mode": "hourly",
    "hours": 6,
    "fogos_url": "https://fogos.pt/fogo/2026123456789"
  },
  "features": [
    {
      "type": "Feature",
      "geometry": { "type": "Polygon", "coordinates": [ /* ... */ ] },
      "properties": {
        "hour": 1,
        "disclaimer": "Cenário de propagação PyroCast...",
        "uncertainty_bands": { /* opcional, ver §7 */ },
        "burn_probability_bands": { /* opcional, ver §7 */ }
      }
    },
    { /* hour 2 */ },
    { /* hour 3 */ },
    { /* hour 4 */ },
    { /* hour 5 */ },
    { /* hour 6 */ }
  ]
}
```

### O que fazer
- **Isócronas são cumulativas**: hour 6 contém hour 5, que contém hour 4, etc. Renderizar por **ordem inversa** (6→1) para que a isócrona mais próxima fique por cima. Ou usar gradiente de cor keyed em `properties.hour`.
- Se o frontend tinha lógica "se `features.length === 0`, esconder o painel de simulação", confirmar que passa a mostrar quando há dados.
- O endpoint global (`/v2/fire/simulation`) devolve **uma** simulação a cada momento — a última bem-sucedida. Se o UI implicava "várias simulações", ajustar. Para simulações de um incidente específico usar sempre o per-incident.

---

## 4. `X-Stale: true` header em vez de esconder dados antigos 🟠

### Contexto
A cache Redis do lado do servidor passou de 1h/2h para 24h porque em situações de crise (o pior momento para o mapa ficar vazio) o upstream do FOCO pode estar em baixo mais do que 1 hora. A UX passou a ser "mostrar dados antigos com aviso" em vez de "não mostrar nada".

### Header novo
Todos os endpoints globais (`/v2/fire/perimeters`, `/v2/fire/simulation`, `/v2/fire/perimeters/uncorrelated`) devolvem:

- `X-Fetched-At: 2026-09-24T14:15:00+00:00` — quando o servidor recebeu esses dados do FOCO.
- `X-Stale: true` — presente **só** quando `X-Fetched-At` já tem > 30 min. Ausente quando fresco.

### O que fazer
```js
const stale = response.headers.get('X-Stale') === 'true';
const fetchedAt = response.headers.get('X-Fetched-At');

if (stale) {
  // Mostrar badge tipo "⚠️ Dados de há 47 min — FOCO indisponível"
  const age = Date.now() - new Date(fetchedAt).getTime();
  showStaleBanner(formatAge(age));
}
```

Sugestão: badge amarelo/laranja discreto sobre o layer, não modal a bloquear. Manter os dados renderizados — a alternativa (mapa vazio) é pior.

---

## 5. Campo `errors` em `/v2/incidents/{id}/perimeter` 🟠

### Contexto
Alguns incidentes ANEPC vêm sem coordenadas utilizáveis (mal-geocoded, sem `latitude`/`longitude`) — o FOCO reporta isto como um erro por incidente em vez de silêncio. A API expõe-o para o frontend distinguir "sem perímetro possível" de "ainda a processar".

### Payload

**Caso normal (tem perímetro):**
```json
{
  "type": "FeatureCollection",
  "fetched_at": "2026-09-24T14:15:00+00:00",
  "disclaimer": "Evidência de satélite MTG...",
  "features": [ { /* uma feature com o polígono */ } ]
}
```

**Caso "sem perímetro possível" (novo):**
```json
{
  "type": "FeatureCollection",
  "fetched_at": "2026-09-24T14:15:00+00:00",
  "disclaimer": "Evidência de satélite MTG...",
  "features": [],
  "errors": [
    {
      "code": "no_usable_coordinates",
      "message": "Incident coordinates outside MTG coverage or invalid"
    }
  ]
}
```

**Caso "ainda não há dados" (inalterado):**
```json
{
  "type": "FeatureCollection",
  "features": []
}
```

### O que fazer
Na página do incidente, quando `/v2/incidents/{id}/perimeter` responde:

```js
if (data.features.length > 0) {
  renderPerimeter(data.features[0]);
} else if (data.errors && data.errors.length > 0) {
  // Novo: mostrar razão
  showBanner(`Sem perímetro possível: ${data.errors[0].message}`);
} else {
  // Como antes: "ainda não há detecção MTG para este incidente"
  showEmptyState();
}
```

---

## 6. Cadência de poll — 10 min em vez de 15 🟢

O job de ingestão passou de `everyFifteenMinutes()` para `everyTenMinutes()` no servidor porque o produto MTG publica em slots de 10 min. O frontend não é obrigado a mudar, mas se estiver a fazer poll do endpoint global, alinhar com 10 min evita mostrar dados 5 min mais velhos que o necessário.

Alternativa: usar o `X-Fetched-At` para decidir "só refetch se o payload local for mais antigo que X-Fetched-At do servidor" (poupa transferência quando o servidor ainda não tem nada novo).

---

## 7. (Opcional) Bandas de incerteza da simulação 🟢

Passámos a pedir `perturb: true` ao motor PyroCast. Isso significa que cada feature da simulação pode trazer:

- `properties.uncertainty_bands` — envelope de incerteza sobre a isócrona (polígono mais largo).
- `properties.burn_probability_bands` — probabilidades de queima em bandas (`10%`, `25%`, `50%`, `75%`, `90%` — depende do que o motor devolver).

Se o UI quiser mostrar "a isócrona +3h está aqui, com incerteza para aqui", tem material para tal. É opcional — a isócrona base (`geometry`) continua a ser suficiente.

---

## Checklist de teste (staging)

Antes de dar por concluída a integração:

- [ ] Chamar `/v2/fire/perimeters/uncorrelated`, confirmar que devolve um `FeatureCollection` com features onde `fogospt_incident` é `null`.
- [ ] Chamar `/v2/fire/perimeters`, confirmar que **nenhuma** feature tem `fogospt_incident: null`.
- [ ] Verificar que o texto do `disclaimer` aparece no UI (top-level e em popups).
- [ ] Chamar `/v2/fire/simulation` com incêndios activos em staging — deve devolver feature(s), **não** vazio (se for vazio, o commit `5ad9e19` não está deployado ainda; confirmar com backend).
- [ ] Verificar que o header `X-Stale` só aparece quando o dado é > 30 min — testar forçando `SCHEDULER_ENABLE=false` no servidor e esperar 31 min.
- [ ] Para um incidente conhecido sem coordenadas MTG utilizáveis, verificar que `/v2/incidents/{id}/perimeter` devolve `errors: [...]` e não `features: []` silencioso.
- [ ] Testar com um perímetro `MultiPolygon` — a serialização do frontend não pode assumir `Polygon`.
- [ ] Testar simulação com `properties.uncertainty_bands` presente — não deve falhar mesmo que ignore o campo.

---

## Referências

- Backend integration doc: [`docs/integration-foco-vost.md`](./integration-foco-vost.md)
- OpenAPI paths afectadas: `docs/fires/paths/v2-fire-*.yaml`, `docs/fires/paths/v2-incident-{perimeter,simulation}.yaml`, `docs/fires/paths/v2-fire-perimeters-uncorrelated.yaml`
- Commits: `5ad9e19` (fix bloqueante simulação), `1ef6a06` (migração para `/active` + cadência + TTL), `e76e0ef` (disclaimers + errors + uncorrelated + testes)
- Revisão original do FOCO: fornecida pelo agente da equipa `foco.vost.pt` a 2026-09-23
