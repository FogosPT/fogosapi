# Integração: avisos meteo do distrito no payload do incidente

Guia dirigido ao **agente que vai implementar** a exibição dos avisos meteorológicos IPMA activos junto de cada incidente no frontend.

O backend já resolve a correlação distrito → área de aviso IPMA e injecta os avisos no próprio payload de incidente — **o frontend não precisa de fazer um segundo pedido a `/v2/warnings/ipma`** só para saber que avisos afectam um dado incidente.

---

## 1. Base URL e headers

Base: `https://api.fogos.pt`

Headers obrigatórios em qualquer chamada:

| Header | Valor |
|---|---|
| `User-Agent` | Identificável e personalizado. Ex.: `MeuApp/1.0 (+contacto@dominio.pt)`. Nunca deixar o default de bibliotecas (`Guzzle/7`, `node-fetch/x`, `okhttp/x`, `python-requests/x`). |
| `Accept` | `application/json` |

**Consequência de incumprimento:** pedidos podem ser limitados ou bloqueados sem aviso prévio.

---

## 2. O campo `weatherWarnings`

Todos os endpoints listados em §3 devolvem, para cada incidente, um novo campo:

```jsonc
{
  "id": "202600001234",
  "district": "Bragança",
  // …outros campos do incidente…
  "weatherWarnings": [
    {
      "text": "Precipitação por vezes forte, podendo ser acompanhada de trovoada.",
      "awarenessTypeName": "Precipitação",
      "idAreaAviso": "BGC",
      "awarenessLevelID": "yellow",
      "startTime": "2026-09-23T18:00:00",
      "endTime":   "2026-09-24T06:00:00"
    },
    {
      "text": "Vento forte de sudoeste.",
      "awarenessTypeName": "Vento",
      "idAreaAviso": "BGC",
      "awarenessLevelID": "orange",
      "startTime": "2026-09-23T21:00:00",
      "endTime":   "2026-09-24T09:00:00"
    }
  ]
}
```

### Contrato do campo

| Chave | Tipo | Notas |
|---|---|---|
| `text` | `string` | Texto humano em português, tal como publicado pelo IPMA. |
| `awarenessTypeName` | `string` | Tipo do aviso: `Precipitação`, `Vento`, `Trovoada`, `Neve`, `Nevoeiro`, `Tempo Frio`, `Tempo Quente`, `Agitação Marítima`, `Radiação UV`. **Não é enum estável** — tratar como string arbitrária no render. |
| `idAreaAviso` | `string` | Código IPMA de 3 letras da área de aviso (ex.: `BGC`, `AVR`, `LSB`). Sempre o mesmo para todos os itens do array de um dado incidente continental. |
| `awarenessLevelID` | `string` | **Enum:** `yellow` \| `orange` \| `red`. **`green` nunca aparece** — o backend não persiste avisos verdes. |
| `startTime` | `string` (ISO 8601, sem timezone) | Hora local de Portugal (Europe/Lisbon). Formato exacto: `Y-m-d\TH:i:s`. |
| `endTime`   | `string` (ISO 8601, sem timezone) | Idem. Todos os avisos devolvidos têm `endTime >= agora`. |

### Garantias

- **Ordenação:** ascendente por `startTime`.
- **Dedup:** o backend aplica dedup por `(idAreaAviso, awarenessTypeName, awarenessLevelID, startTime, endTime, text)` — o frontend não precisa de o repetir.
- **Frescura:** o job `HandleWeatherWarnings` corre a cada 15 minutos, portanto os dados têm no máximo ~15 min de latência.
- **Array vazio:** `[]` é o valor devolvido quando:
  - o distrito do incidente não tem avisos activos, ou
  - o distrito é desconhecido/vazio, ou
  - o incidente é dos arquipélagos (**Madeira/Açores actualmente devolvem sempre `[]`** — ver §5).
- **Nunca `null`:** o campo existe sempre como array. Testar sempre com `.length` / `isEmpty`, nunca com truthy check.

### Mapeamento distrito → `idAreaAviso` (continental)

O backend usa esta tabela (fonte: `warnings_www.json` do IPMA):

| Distrito | Código |
|---|---|
| Aveiro | `AVR` |
| Beja | `BJA` |
| Braga | `BRG` |
| Bragança | `BGC` |
| Castelo Branco | `CBO` |
| Coimbra | `CBR` |
| Évora | `EVR` |
| Faro | `FAR` |
| Guarda | `GDA` |
| Leiria | `LRA` |
| Lisboa | `LSB` |
| Portalegre | `PTG` |
| Porto | `PTO` |
| Santarém | `STM` |
| Setúbal | `STB` |
| Viana do Castelo | `VCT` |
| Vila Real | `VRL` |
| Viseu | `VIS` |

O frontend **não precisa** desta tabela para consumir `weatherWarnings` — só para render se quiser mostrar o nome legível da área ao lado do código. Também dá para simplesmente usar o `district` do próprio incidente.

---

## 3. Endpoints que devolvem `weatherWarnings`

Todos os endpoints abaixo servem o `IncidentResource`, portanto todos incluem o novo campo — o frontend não precisa de mudar de endpoint, só de ler o campo novo.

### v2 (recomendado)

| Método | Path | Uso |
|---|---|---|
| `GET` | `/v2/incidents/active` | Lista de incidentes activos. Suporta `?geojson=true`, `?limit=N`, `?concelho=…`, `?subRegion=…`, `?all=true`, `?fma=true`, `?otherFires=true`. Resposta: `{ success, data: [incidents] }` (ou GeoJSON `FeatureCollection` se `geojson=true`, com `weatherWarnings` dentro de `properties`). |
| `GET` | `/v2/incidents/search?…` | Pesquisa filtrada. Resposta paginada: `{ success, paginator, data: [incidents] }`. |
| `GET` | `/v2/incidents/1000ha-burned` | Incêndios com >1000 ha ardidos. Resposta: `{ success, data: [incidents] }`. |

**Exportações CSV (`?csv=1` e `?csv2=1`) omitem propositadamente `weatherWarnings`** (tal como já omitem `weather`) — não é possível serializar um array de objectos em CSV. Se precisares de flat, fazes um segundo endpoint próprio.

### Legacy (mantido para compat)

| Método | Path | Uso |
|---|---|---|
| `GET` | `/new/fires` | Lista de incêndios activos, formato legacy. `{ success, data: [incidents] }`. |
| `GET` | `/fires?id={id}` | Um incidente por ID. `{ success, data: incident }`. |

Se estás a começar do zero, usa **sempre v2**. Legacy só é referido aqui por completude.

### Endpoints que **não** têm `weatherWarnings`

Ficam como estavam:
- `/v1/*` (LegacyController: `/v1/now`, `/v1/active`, `/v1/status`, …) — não usam `IncidentResource`, devolvem shape antigo directo do Mongo.
- `/v2/incidents/{id}/kml*`, `/v2/incidents/{id}/perimeter`, `/v2/incidents/{id}/simulation`, `/v2/incidents/{id}/photos` — devolvem KML/GeoJSON/lista de fotos, não o incidente.
- Todos os endpoints de meteorologia, RCM, aeronaves, avisos, moderação, etc.

Se precisares do detalhe de um incidente por ID com `weatherWarnings`, usa `/fires?id={id}` (legacy) ou filtra a listagem de `/v2/incidents/active` — ainda **não existe** um endpoint `GET /v2/incidents/{id}`.

---

## 4. Padrões de uso no frontend

### 4.1. Badge/pill no card do incidente

```tsx
function IncidentWarningBadges({ warnings }: { warnings: WeatherWarning[] }) {
  if (warnings.length === 0) return null;

  const colorFor = (level: 'yellow' | 'orange' | 'red') =>
    ({ yellow: '#F5C518', orange: '#F17300', red: '#D62828' }[level]);

  return (
    <div className="flex gap-1">
      {warnings.map((w, i) => (
        <span
          key={i}
          title={`${w.awarenessTypeName}: ${w.text}`}
          style={{ background: colorFor(w.awarenessLevelID) }}
          className="rounded-full px-2 py-0.5 text-xs text-white"
        >
          {w.awarenessTypeName}
        </span>
      ))}
    </div>
  );
}
```

### 4.2. Nível mais grave (para colorir o card inteiro)

```ts
const LEVEL_RANK = { yellow: 1, orange: 2, red: 3 } as const;

function highestLevel(warnings: WeatherWarning[]) {
  if (warnings.length === 0) return null;
  return warnings.reduce((max, w) =>
    LEVEL_RANK[w.awarenessLevelID] > LEVEL_RANK[max.awarenessLevelID] ? w : max
  );
}
```

### 4.3. Filtrar só avisos activos "agora" (opcional)

O backend já garante `endTime >= agora` no momento da resposta, mas se cacheares o payload no cliente durante minutos e quiseres eliminar avisos que expiraram entretanto:

```ts
const now = new Date();
const active = warnings.filter(w => new Date(w.endTime) >= now);
```

Nota: `startTime` pode ser no futuro — um aviso pode estar "programado" mas ainda não ter começado. Se só quiseres os que já estão em curso agora, filtra também por `startTime <= now`.

### 4.4. Timezone

`startTime`/`endTime` são strings **sem** sufixo de timezone e representam **hora local de Portugal continental** (Europe/Lisbon). Ao converter para `Date` em JS:

```ts
// CUIDADO: `new Date('2026-09-23T18:00:00')` interpreta como hora local do browser.
// Se o utilizador estiver noutro fuso, o cálculo fica errado.
// Se precisares de garantir Lisboa:
import { parse } from 'date-fns';
import { fromZonedTime } from 'date-fns-tz';

const utc = fromZonedTime(w.startTime, 'Europe/Lisbon');
```

Para render simples ao utilizador em Portugal, o `new Date(...)` directo chega.

---

## 5. Limitações conhecidas

- **Madeira e Açores devolvem sempre `[]`.** As áreas de aviso do IPMA nas ilhas são sub-regionais (Madeira: `MCN`/`MCS`/`MRM`/`MPS`; Açores: `AOC`/`ACE`/`AOR`) e não mapeiam 1:1 do nome do distrito ANEPC. Se o frontend precisar de avisos para as ilhas, consumir directamente `/v2/warnings/ipma` e correlacionar por conta própria.
- **`awarenessTypeName` não é enum estável.** O IPMA pode introduzir novos tipos sem aviso. Renderizar como string; se quiseres ícones, ter fallback para tipos desconhecidos.
- **Não há histórico.** `weatherWarnings` mostra só os **actualmente activos**. Avisos passados não são devolvidos aqui — se precisares, faz query directa ao Mongo (`weather_warnings`).
- **Sem `awarenessLevelID: "green"`.** Ausência de avisos ≠ "verde explícito" — é simplesmente array vazio.

---

## 6. Checklist de implementação frontend

1. Adicionar o tipo `WeatherWarning` ao modelo `Incident` no cliente.
2. Renderizar badges por incidente quando `weatherWarnings.length > 0`.
3. Aplicar cor de fundo/borda ao card com base no nível mais grave (`highestLevel()`).
4. Testar com um incidente em distrito com aviso activo (ex.: em pico de calor há sempre um continental) **e** com um incidente em Madeira/Açores para confirmar que o array vazio é tratado como "sem avisos" e não como erro.
5. Não alterar chamadas existentes a `/v2/warnings/ipma` — esse endpoint continua a servir a lista completa nacional para páginas dedicadas a avisos.

---

## 7. Perguntas ao backend

Se precisares de algo não coberto aqui — endpoint dedicado por incidente, avisos históricos, mapeamento das ilhas — abrir issue em `fogosapi` antes de inventar workarounds no cliente.
