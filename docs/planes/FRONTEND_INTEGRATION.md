# Tracking de aviões de incêndio — integração frontend

Documento para a equipa que vai pôr os meios aéreos do DECIR no mapa do site e das apps mobile.

---

## TL;DR

- **Endpoint principal:** `GET /v2/planes/recent?hours=6` — devolve as últimas N horas de posições, agrupadas por avião. Usa este para desenhar marcadores + tracks no mapa.
- **Refresh recomendado no cliente:** **60 segundos**. O backend cacha durante 60 s e atualiza FR24 a cada 3 min — pedidos mais frequentes são desperdício.
- **Sem autenticação.** Endpoints públicos, mesmo padrão dos outros `/v2/*`.
- **Backend só pode estar a alimentar a coleção entre `sunrise + 1h` e `sunset − 1h`** (Lisboa) e quando há incêndios aéreos ativos. Fora disso `positions` pode vir vazio mesmo para aviões marcados como ativos — é comportamento esperado, não tratar como erro.

---

## Endpoints

Base URL: `https://api.fogos.pt`

### 1. `GET /v2/planes/recent?hours=N` — **endpoint principal**

Posições das últimas N horas, agrupadas por aeronave. Pronto para desenhar tracks no mapa.

**Query params**
| Param | Tipo | Default | Limites | Descrição |
|---|---|---|---|---|
| `hours` | int | `6` | 1–24 | Janela temporal |

**Resposta (200)**
```json
{
  "success": true,
  "data": [
    {
      "icao": "495229",
      "registration": "CS-INM",
      "name": "Helibombeiro 1",
      "aircraft_type": "AS50",
      "base": "LPMT",
      "operator": "Heliportugal",
      "positions": [
        {
          "lat": 38.7223,
          "lon": -9.1393,
          "altitude": 4200,
          "ground_speed": 110,
          "track": 270,
          "on_ground": false,
          "sampled_at": "2026-06-30T12:00:00.000000Z",
          "created": "2026-06-30T12:00:05.000000Z"
        },
        { "...mais posições..." }
      ]
    }
  ]
}
```

**Notas:**
- `positions` está **ordenado cronologicamente (mais antigo primeiro)**, pronto para alimentar diretamente uma `Polyline`.
- Se um avião do catálogo não teve posições na janela pedida, vem com `positions: []` na mesma. Filtrar no cliente.
- Cache de 60 s no backend.

---

### 2. `GET /v2/planes` — catálogo + última posição

Lista todos os aviões rastreados; cada um traz a sua **última** posição conhecida (ou `null` se nunca foi visto).

**Resposta (200)**
```json
{
  "success": true,
  "data": [
    {
      "icao": "495229",
      "registration": "CS-INM",
      "name": "Helibombeiro 1",
      "aircraft_type": "AS50",
      "base": "LPMT",
      "operator": "Heliportugal",
      "is_flying": true,
      "last_seen_minutes_ago": 2,
      "last_position": {
        "lat": 38.7223,
        "lon": -9.1393,
        "altitude": 4200,
        "ground_speed": 110,
        "track": 270,
        "on_ground": false,
        "sampled_at": "2026-06-30T12:00:00.000000Z",
        "created": "2026-06-30T12:00:05.000000Z"
      }
    }
  ]
}
```

`is_flying` = `true` quando a última posição é de há ≤ 10 min. `last_position` pode ser `null`.

---

### 3. `GET /v2/planes/active` — só os que estão a voar agora

Mesma shape de `/v2/planes`, mas só inclui aeronaves com `is_flying = true`.

Útil se quiseres uma lista lateral "a voar agora" sem teres de filtrar no cliente.

---

### 4. `GET /v2/planes/{icao}/track` — track detalhado de uma aeronave

Últimas 20 posições FR24 de um avião específico (em ordem **descendente**, mais recente primeiro).

**Path param**
| Param | Tipo | Descrição |
|---|---|---|
| `icao` | string | ICAO 24-bit hex em minúsculas (ex: `495229`) |

**Resposta (200)**
```json
{
  "success": true,
  "data": [
    {
      "icao": "495229",
      "registration": "CS-INM",
      "callsign": "BMB01",
      "aircraft_type": "AS50",
      "lat": 38.7223,
      "lon": -9.1393,
      "altitude": 4200,
      "ground_speed": 110,
      "vertical_speed": -300,
      "track": 270,
      "squawk": "7700",
      "on_ground": false,
      "sampled_at": "2026-06-30T12:00:00.000000Z",
      "created": "2026-06-30T12:00:05.000000Z"
    }
  ]
}
```

---

### 5. `GET /v2/planes/{icao}` — legacy

Endpoint antigo, mantém-se por retrocompatibilidade. **Lê de outra coleção** (`pplanes`, dados ADS-B Exchange, que neste momento não está a receber dados novos). **Não usar para tracking ao vivo** — usar `/v2/planes/{icao}/track`.

---

## Semântica dos campos

| Campo | Tipo | Unidade / Formato | Notas |
|---|---|---|---|
| `icao` | string | 6 hex chars, lowercase | ICAO 24-bit transponder address |
| `registration` | string | ex. `CS-INM` | Matrícula |
| `callsign` | string \| null | ex. `BMB01` | Pode mudar entre voos |
| `aircraft_type` | string | Type code livre (`AS350B3`, `AT-802A`, `CL215`, …) | Não usar como classificador — usa `kind` |
| `kind` | string | `"airplane"` ou `"helicopter"` | **Usa este campo para escolher o ícone no mapa.** Default: `"airplane"` quando não classificado |
| `base` | string \| null | ICAO airfield code (`LPMT`, `LPCO`, …) | Base operacional |
| `operator` | string \| null | Nome do operador | Texto livre |
| `lat`, `lon` | number | WGS84 graus decimais | Lat/Lng directo para Leaflet, Mapbox, Google Maps |
| `altitude` | int \| null | feet | Altitude barométrica |
| `ground_speed` | int \| null | knots | 1 kt ≈ 1,852 km/h |
| `vertical_speed` | int \| null | feet/min | Positivo = subida, negativo = descida |
| `track` | int \| null | graus (0–359) | True heading. Usa para rodar o ícone do avião. 0 = Norte. |
| `squawk` | string \| null | 4 dígitos | Código transponder |
| `on_ground` | bool | — | `true` quando a aeronave está no solo |
| `sampled_at` | string (ISO 8601 UTC) | — | Quando o FR24 observou |
| `created` | string (ISO 8601 UTC) | — | Quando o nosso backend persistiu |

---

## Padrão de uso recomendado para o mapa

```js
const REFRESH_MS = 60_000;

async function refreshPlanes() {
  const res = await fetch('https://api.fogos.pt/v2/planes/recent?hours=6');
  const { data } = await res.json();

  for (const plane of data) {
    if (plane.positions.length === 0) continue;

    const last = plane.positions[plane.positions.length - 1];
    drawMarker({
      id: plane.icao,
      lat: last.lat,
      lng: last.lon,
      rotation: last.track,
      icon: plane.kind === 'helicopter' ? heliIcon : airplaneIcon,
      popup: `${plane.name} (${plane.registration}) — ${last.altitude} ft, ${last.ground_speed} kt`,
    });

    drawPolyline({
      id: plane.icao,
      points: plane.positions.map(p => [p.lat, p.lon]),
    });
  }
}

refreshPlanes();
setInterval(refreshPlanes, REFRESH_MS);
```

**Detalhes a ter em conta:**
- Rotação do marcador = `track` graus (0 = norte, sentido horário). Se a tua biblioteca usa convenção diferente, ajusta.
- Avião com `positions: []` no payload: não desenhar (não foi visto na janela).
- Avião com última posição há mais de ~10 min: opcionalmente desenhar a cinzento/transparente em vez de a cor "ativo". O `/v2/planes` já trazia `is_flying`/`last_seen_minutes_ago` calculados — em `/recent` calculas a partir de `created` da última posição.
- Polling: `setInterval(60_000)`. O backend cacha durante 60 s e refresca a cada 3 min, qualquer coisa mais agressiva no cliente só serve resposta cacheada.
- Re-renderizar tracks pode ser pesado — considera "incremental": guardar `last_created` por aeronave e só adicionar pontos novos. Mas para começar, redesenhar tudo a cada refresh chega.

---

## Comportamento do backend (o que esperar)

O backend ingere posições de **três fontes em paralelo**, todas a escrever para a mesma coleção `flight_positions` (campo `source` distingue):

| Source | Cadência | Minutos | Quando corre | Notas |
|---|---|---|---|---|
| `fr24` | cada 3 min | 0, 3, 6… | janela diurna + incêndios aéreos ativos + budget OK | Premium, mais fiável; cobertura completa |
| `airplanes.live` | cada 3 min | 1, 4, 7… | janela diurna | Grátis, depende de cobertura ADS-B comunitária |
| `adsb.fi` | cada 3 min | 2, 5, 8… | janela diurna | Grátis, depende de cobertura ADS-B comunitária |

- As três fontes estão **desfasadas em 1 min** entre si. Combinadas dão 1 fetch por minuto, alternando entre fontes — sem bursts de pedidos simultâneos.
- **Janela diurna:** entre `sunrise + 1h` e `sunset − 1h` em Lisboa (calculado dinamicamente).
- **Resultado prático:** durante o dia, com aviões a voar, esperar **~1 posição nova por minuto** por avião (união das três fontes).
- **Fora da janela diurna ou sem fogos aéreos ativos:** sem posições novas. Apresentar como "última posição há X minutos" no UI.
- **Posições nunca expiram** (sem pruning). Pedir `hours=24` é seguro; pedir `hours=72` daria 422 (max = 24).
- O endpoint `/recent` **não** distingue por fonte na resposta — apenas agrega cronologicamente. Se quiseres saber a origem de uma posição específica, usar `/v2/planes/{icao}/track` que devolve o registo cru (com campo `source`).

---

## Erros

Endpoints retornam `200` mesmo com `data: []`. Erros 5xx só em falha de Mongo. O frontend deve:
- Tratar `data: []` como "nada para mostrar".
- Em 5xx: manter o que já estava no mapa e tentar novamente no próximo ciclo.

---

## Referência rápida

| Caso | Endpoint |
|---|---|
| Mapa principal com tracks | `GET /v2/planes/recent?hours=6` |
| Lista lateral "a voar agora" | `GET /v2/planes/active` |
| Página com todos os meios e estado | `GET /v2/planes` |
| Detalhe de uma aeronave (drill-down) | `GET /v2/planes/{icao}/track` |

OpenAPI completo: `docs/api.yaml` no repo do backend (paths em `docs/planes/paths/`).
