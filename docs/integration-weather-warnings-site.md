# Integração: site de avisos meteorológicos → FogosPT API

Guia dirigido ao **agente que vai implementar** os avisos meteorológicos e (opcionalmente) enriquecer o site com dados de estações e observações usando a FogosPT API.

Base URL: `https://api.fogos.pt`

---

## 1. Cabeçalhos obrigatórios em **todos** os pedidos

Conforme <https://fogos.pt/pt/api>, cada request tem de ir com:

| Header | Obrigatoriedade | Valor |
|---|---|---|
| `X-API-Key` | **Obrigatório** | Token individual do site (pedir em <https://fogos.pt/pt/api>). Não partilhar entre projectos. Guardar em variável de ambiente / secret manager — nunca em código versionado nem em JS entregue ao browser. |
| `User-Agent` | **Obrigatório** | Identificável e personalizado. Ex.: `AvisosMeteoMeuSite/1.0 (+contacto@dominio.pt)`. Não deixar o default de bibliotecas (`Guzzle/7`, `node-fetch/x`, `okhttp/x`, `python-requests/x`, etc.). |
| `X-Origin-IP` | Recomendado | IP público do servidor consumidor, quando fixo. |

**Consequência de incumprimento:** pedidos podem ser limitados ou bloqueados sem aviso prévio.

**Onde aplicar:** num cliente HTTP **partilhado** (interceptor / middleware / factory), nunca a copiar headers em cada call site.

Se o site tem chamadas a partir do frontend público (browser), **não expor o `X-API-Key`** — as chamadas têm de passar por um proxy do lado do servidor.

Exemplo (Node/Express a fazer proxy do lado do servidor):

```js
const FOGOS = {
  base: 'https://api.fogos.pt',
  headers: {
    'X-API-Key': process.env.FOGOS_API_KEY,
    'User-Agent': 'AvisosMeteoMeuSite/1.0 (+contacto@dominio.pt)',
  },
};

async function fogosGet(path) {
  const res = await fetch(FOGOS.base + path, {
    headers: FOGOS.headers,
    signal: AbortSignal.timeout(30_000),
  });
  if (!res.ok) throw new Error(`FogosPT ${path} → ${res.status}`);
  return res.json();
}
```

---

## 2. Endpoint principal — Avisos meteorológicos

`GET /v2/warnings/ipma`

Substitui o consumo directo de `https://api.ipma.pt/open-data/forecast/warnings/warnings_www.json`.

**Resposta:** array de objectos com o mesmo shape do IPMA:

```json
[
  {
    "text": "Períodos de chuva ou aguaceiros...",
    "awarenessTypeName": "Precipitação",
    "idAreaAviso": "BGC",
    "startTime": "2026-08-26T13:49:00",
    "awarenessLevelID": "yellow",
    "endTime": "2026-08-26T18:00:00"
  }
]
```

**Diferenças conhecidas face ao IPMA:**
- Só devolve warnings com `endTime >= agora` (o site pode assumir que tudo o que recebe está activo/pendente).
- Nunca contém nível `green` (não são ingeridos).
- Ordenado por `startTime` ascendente.

**Frescura:** o backend ingere avisos a cada 15 minutos. Não vale a pena o site cachear mais que ~5 min.

**Códigos:** `200` sempre com array (vazio se não houver avisos activos). `5xx` em falha interna.

**Correlação com áreas geográficas:** `idAreaAviso` é o código de área de aviso do IPMA (ex.: `BGC` = Bragança, `AVR` = Aveiro). O site precisa da sua própria tabela `codigo → nome amigável` para render — o endpoint devolve o código bruto.

---

## 3. Endpoint auxiliar — Catálogo de estações

`GET /v2/weather/stations/ipma`

Devolve todas as estações meteorológicas do IPMA em formato GeoJSON Feature, idêntico ao upstream `https://api.ipma.pt/open-data/observation/meteorology/stations/stations.json`:

```json
[
  {
    "type": "Feature",
    "geometry": { "type": "Point", "coordinates": [-7.821, 37.033] },
    "properties": { "idEstacao": 1210881, "localEstacao": "Olhão, EPPO" }
  }
]
```

**Coordenadas:** `[longitude, latitude]` (convenção GeoJSON), **atenção à ordem** — Leaflet/Mapbox exigem inverter para `[lat, lng]` em muitos APIs.

**Uso típico no site:**
- Renderizar estações num mapa.
- Construir um lookup `idEstacao → { nome, lat, lng }` para depois enriquecer as observações da §4 (que só trazem o `stationId`).

**Frescura:** o catálogo muda muito raramente. Cache do servidor 1 h; do lado do site basta refrescar 1x/dia.

---

## 4. Endpoint auxiliar — Observações horárias (últimas 24 h)

`GET /v2/weather/observations`

Devolve as últimas 24 horas de observações agrupadas por hora UTC, com o mesmo shape de `https://api.ipma.pt/open-data/observation/meteorology/stations/observations.json`:

```json
{
  "2026-08-26T13:00": {
    "1210881": {
      "intensidadeVentoKM": 15.5,
      "temperatura": 23.4,
      "radiacao": 0.0,
      "idDireccVento": 7,
      "precAcumulada": 0.0,
      "intensidadeVento": 4.3,
      "humidade": 67.0,
      "pressao": 1014.0
    }
  }
}
```

**Chaves de topo:** timestamps `YYYY-MM-DDTHH:mm` **em UTC** (sem timezone no string). Converter para hora local (Europe/Lisbon) na apresentação.

**Campos e unidades:**

| Campo | Unidade | Descrição |
|---|---|---|
| `temperatura` | °C | Temperatura do ar. |
| `humidade` | % | Humidade relativa. |
| `pressao` | hPa | Pressão atmosférica. |
| `precAcumulada` | mm | Precipitação acumulada na hora. |
| `intensidadeVento` | m/s | Velocidade do vento. |
| `intensidadeVentoKM` | km/h | Velocidade do vento. |
| `idDireccVento` | id 0–9 | Direcção do vento (ver mapa abaixo). |
| `radiacao` | kJ/m² | Radiação solar. |

**Mapa de `idDireccVento`:**
`0 = null (sem vento) · 1 = N · 2 = NE · 3 = E · 4 = SE · 5 = S · 6 = SW · 7 = W · 8 = NW · 9 = N`

**Sentinela de valor em falta:** `-99` em qualquer campo numérico significa **"sem leitura"** — não é zero, não é um valor válido. Filtrar antes de calcular médias, agregações ou render.

**Estações omissas:** se uma estação não tiver nenhuma leitura numa determinada hora, **não aparece** nessa hora (o IPMA reporta-a como `null` — nós simplesmente omitimos). O site tem de tolerar chaves ausentes.

**Frescura:** o backend actualiza de hora a hora; cache do servidor 10 min. Cache do site: 5–10 min.

**Uso típico ligado aos avisos:**
1. Buscar `/v2/warnings/ipma` para render dos avisos.
2. Buscar `/v2/weather/stations/ipma` uma vez (cache longa) para o catálogo.
3. Buscar `/v2/weather/observations` para render de leituras actuais junto ao aviso da região (ex.: card do aviso mostra também a temperatura/vento da estação mais próxima).

**Correlação warning ↔ estação:** o warning traz `idAreaAviso` (código de área IPMA); as estações estão georreferenciadas mas **não** têm campo de área de aviso. A correlação é espacial — o site tem de manter a sua própria tabela de bounding boxes / polígonos por `idAreaAviso`, ou simplesmente mostrar as observações num mapa geral.

---

## 5. Checklist de implementação

Executar por esta ordem:

1. **Obter `FOGOS_API_KEY`** em <https://fogos.pt/pt/api>. Registar em secret manager, uma chave por ambiente (dev/staging/prod).
2. **Criar cliente HTTP partilhado** com `base_uri`, `X-API-Key`, `User-Agent`, timeouts (`connect_timeout=10s`, `timeout=30s`) — ver §1.
3. **Implementar página/componente de avisos** consumindo `/v2/warnings/ipma`. Renderizar `awarenessLevelID` como cor (yellow/orange/red), `awarenessTypeName` como ícone/label, `idAreaAviso` traduzido para nome da região via tabela local.
4. **Adicionar mapa/lista de estações** consumindo `/v2/weather/stations/ipma` (opcional, 1x/dia). Guardar em memória / cache local para servir de lookup.
5. **Enriquecer com observações** de `/v2/weather/observations`:
   - Iterar defensivamente sobre as horas e stationIds presentes.
   - Filtrar `-99` antes de mostrar/agregar.
   - Cruzar `stationId` com o catálogo da §4 para obter nome e coordenadas.
6. **Logging** dos códigos de resposta (`200`, `401`, `403`, `429`, `5xx`) na camada do cliente. Alerta em `4xx` — significa problema com o token ou User-Agent.
7. **Fail loud:** se o token não estiver definido, o site deve falhar no arranque, não silenciosamente cair em cache/mock.
8. **README do site** actualizado com `FOGOS_API_KEY` como variável obrigatória e link para <https://fogos.pt/pt/api>.

---

## 6. Gotchas resumidos

- **Timezone:** os timestamps das observações são UTC sem sufixo; os das warnings são horas locais de Portugal continental sem sufixo (formato IPMA). Não misturar.
- **`-99`** em observações = sem leitura. Nunca tratar como valor real.
- **`green`** nunca aparece em warnings — se o site tinha lógica para "sem avisos = green" isso continua a valer, mas por ausência de entradas.
- **Coordenadas GeoJSON** são `[lng, lat]`, não `[lat, lng]`.
- **`X-API-Key` no browser é falha de segurança** — todas as chamadas têm de sair do servidor do site.
- **Rate-limiting:** ainda não há números públicos, mas espera-se que consumidores "razoáveis" (poll a cada 5–15 min por endpoint) não sejam limitados. Poll mais agressivo requer justificação prévia.
