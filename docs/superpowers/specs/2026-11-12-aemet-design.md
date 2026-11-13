# AEMET (Espanha): avisos e estações meteorológicas — desenho

Issue: #102 · Estado: rascunho para aprovação

## Porquê

Os fenómenos meteorológicos não olham para fronteiras. Quem acompanha os avisos de
Bragança, da Guarda ou de Faro no avisos.pt quer saber se do outro lado também há
aviso e com que severidade. A fogosapi já importa os avisos e as estações do IPMA;
passa a importar também os da AEMET (Agencia Estatal de Meteorología).

Dois pedidos explícitos do utilizador:
- **endpoints próprios para Espanha** — nada se mistura nos endpoints do IPMA;
- **normalizados para serem iguais aos do IPMA** — mesmo formato de resposta, mesmas
  chaves, mesmas unidades, mesmos parâmetros e mesma semântica, para que um consumidor
  dos endpoints do IPMA (o avisos.pt) use os de Espanha com o mesmo código.

Consumidor principal: avisos.pt — zonas de aviso espanholas coloridas no `/mapa`
(toda a Espanha peninsular) e uma secção "Do outro lado da fronteira" nas páginas dos
distritos raianos. Esse trabalho tem desenho próprio, depois deste; este documento só
cobre a fogosapi.

## Objetivos

1. Importar os avisos da AEMET (toda a Espanha), guardando todas as mensagens CAP
   recebidas (histórico completo).
2. Servir os avisos em vigor e o registo de alterações exatamente como
   `v2/warnings/ipma` e `v2/warnings/ipma/history`.
3. Servir a geometria das zonas de aviso.
4. Importar o inventário, as observações horárias e os resumos diários das estações da
   AEMET e servi-los exatamente como os endpoints de estações do IPMA.

## Não-objetivos

- Mostrar o que quer que seja no avisos.pt (desenho seguinte).
- Normais climatológicas e deteção de ondas de calor/frio para Espanha
  (`v2/weather/waves`).
- Notificações (Telegram, X, Bluesky, push) de avisos espanhóis.
- Equivalentes dos endpoints do IPMA que o avisos.pt não usa e são legado:
  `v2/weather/stations?id=&place=` (documentos crus), `v2/weather/daily?date=`,
  `v2/weather/thunders`, `ipma-services`.

## Anti-objetivos

- Mexer no comportamento ou no formato dos endpoints do IPMA (a refatoração para
  partilhar código com a AEMET não pode mudar nenhuma resposta do IPMA — os testes
  existentes têm de passar sem alterações).
- Pôr a chave da AEMET no repositório (vive só em `.env`).
- Um job que, com a AEMET em baixo ou um feed vazio, apague ou dê por terminados os
  avisos em vigor.

## Correspondência de endpoints

| IPMA | AEMET | Notas |
|---|---|---|
| `GET v2/warnings/ipma` | `GET v2/warnings/aemet` | + filtro `peninsula=1` |
| `GET v2/warnings/ipma/history` | `GET v2/warnings/aemet/history` | mesmos parâmetros |
| — | `GET v2/warnings/aemet/zones` | geometria (o IPMA não tem equivalente na API) |
| `GET v2/weather/stations/ipma` | `GET v2/weather/aemet/stations` | |
| `GET v2/weather/observations` | `GET v2/weather/aemet/observations` | |
| `GET v2/weather/stations/{id}/observations` | `GET v2/weather/aemet/stations/{id}/observations` | |
| `GET v2/weather/stations/{id}/daily` | `GET v2/weather/aemet/stations/{id}/daily` | |

**A única diferença inevitável**: os identificadores. As zonas da AEMET têm códigos
como `713603` / `713603C` (o `idAreaAviso` dos avisos espanhóis) e as estações têm
`idema` alfanumérico (`3469A`) — o `idEstacao`/`stationId` dos endpoints AEMET é uma
**string**, onde no IPMA é inteiro. As rotas `{id}` aceitam `[0-9A-Za-z]+`.

Campos extra são permitidos onde acrescentam informação sem mudar os existentes
(ex.: nome da zona nos avisos); nunca se renomeia nem se muda o tipo/unidade de uma
chave que o IPMA tenha.

## Fonte: AEMET OpenData

- Base `https://opendata.aemet.es/opendata/api/`, chave no header `api_key`
  (`AEMET_API_KEY`). Sem chave os jobs não correm (como `MADEIRA_EVENTS_URL`).
- **Pedido em dois passos**: a API devolve `{descripcion, estado, datos, metadatos}`;
  os dados estão no URL `datos` (curta duração), pedido logo a seguir.
  `estado` ≠ 200 é falha. Sem chave a API responde HTTP 200 com corpo vazio; com
  chave inválida/expirada, 401 — ambos são falha, e o 401 tem mensagem própria no
  Discord ("chave AEMET inválida ou expirada").
- A chave atual é um JWT que **expira a 2027-02-20**; tem de ser renovada em
  https://opendata.aemet.es/centrodedescargas/altaUsuario (email + captcha). Fica
  escrito no CLAUDE.md.
- Rate limit por minuto (HTTP 429, sem número publicado); os jobs fazem 1–2 pedidos
  por execução, longe do limite. 429 = falha da execução, sem retry imediato.
- Respostas JSON em **ISO-8859-15** (converter para UTF-8 antes do `json_decode`); os
  CAP dentro do tar estão em UTF-8.
- Licença (nota legal AEMET, Lei 37/2007): reutilização livre, também comercial,
  citando a fonte — "Fuente: AEMET" / "Información elaborada utilizando, entre otras,
  la obtenida de la Agencia Estatal de Meteorología". Acrescentar ao `NOTICE` e à
  documentação da API (`docs/`).

Formatos verificados a 2026-11-12/13 com a chave real.

## Avisos

### Fonte

`avisos_cap/ultimoelaborado/area/esp` → `datos` é um **tar** (GNU, não comprimido,
`application/x-gtar`) com um ficheiro CAP 1.2 XML por mensagem. Amostra de
2026-11-12: 284 ficheiros, 233 zonas distintas. Ler com `PharData` (a imagem de
produção tem a extensão `phar`; sem dependências novas).

Cada mensagem (`<alert>`):
- `identifier` (único, imutável), `sent`, `msgType` (`Alert` | `Update`; `Cancel`
  possível pela norma), `references` (lista separada por espaços de
  `sender,identifier,sent` das mensagens que esta substitui).
- Dois `<info>`: `es-ES` e `en-GB`. Em cada um: `event`, `headline`, `description`,
  `instruction`, `onset`, `expires`, `effective` (com offset, hora de Madrid),
  `eventCode` `AEMET-Meteoalerta fenomeno` = `"VI;Vientos"`, parâmetros
  `AEMET-Meteoalerta nivel` (`verde|amarillo|naranja|rojo`),
  `AEMET-Meteoalerta parametro` (`"RM;Rachas máximas;80 km/h"`),
  `AEMET-Meteoalerta probabilidad` (`"40%-70%"`).
- Uma ou mais `<area>`: `areaDesc` (nome), um ou mais `<polygon>` (`lat,lon`
  separados por espaços, 2 casas decimais, fechado; na amostra 156 de 1667 áreas
  traziam 2) e geocode `AEMET-Meteoalerta zona` (código).
- O tar inclui mensagens **`verde`** (sem aviso), que cobrem todas as zonas — é daí
  que vem a geometria de todas as zonas — e mensagens já expiradas.

### Zonas

Código = CCAA (2) + província INE (2) + zona (2), com sufixo `C` nas zonas costeiras
(`713603` Miño de Pontevedra, `713603C` Costa - Miño de Pontevedra).
Prefixos `64` Baleares, `65` Canárias, `78` Ceuta, `79` Melilla → `peninsula = false`;
todos os outros → `true`.

Modelo `AemetWarningZone` (coleção `aemetWarningZones`), upsert por `code` a partir de
**todas** as áreas de todas as mensagens (incluindo `verde`) em cada execução:
`code`, `name` (`areaDesc`), `ccaa` (2 primeiros dígitos), `province` (dígitos 3–4),
`coastal` (sufixo `C`), `peninsula`, `geometry` (GeoJSON **MultiPolygon**, lon/lat —
inverter a ordem do CAP), `lastSeenAt`. A geometria é substituída se mudar.

### Arquivo (histórico completo)

Modelo `AemetWarningMessage` (coleção `aemetWarningMessages`), uma por `identifier`,
**nunca alterada depois de criada** (a mensagem CAP é imutável): `identifier`, `sent`,
`msgType`, `references` (array de identifiers), `level`, `phenomenon`, os códigos das
zonas, `onset`/`expires` (UTC) e o XML original (`raw`). Uma mensagem que reaparece em
execuções seguintes não é reescrita. Tudo o que a AEMET publicou fica aqui, incluindo
`verde` e mensagens substituídas.

Índices: `identifier` único; `zones` + `expires`.

### Do CAP para o formato do IPMA

De cada snapshot sai a lista de avisos em vigor, **no formato do feed do IPMA** que
`HandleWeatherWarnings::sync()` já consome:

1. Mensagens substituídas saem: identifier referenciado em `references` de outra
   mensagem do snapshot, ou `Cancel` / referenciada por um `Cancel`.
2. Ficam as de nível `amarillo`, `naranja` ou `rojo` (`verde` nunca é aviso).
3. Cada mensagem dá **um item por zona** (uma mensagem com 16 áreas dá 16 itens):
   - `idAreaAviso` = código da zona;
   - `awarenessTypeName` = tipo IPMA pela tabela abaixo; fenómenos sem equivalente
     ficam com o nome espanhol da AEMET (ex. "Polvo en suspensión") — nunca null;
   - `awarenessLevelID` = `yellow` | `orange` | `red`;
   - `startTime` = `onset`, `endTime` = `expires`;
   - `text` = `description` (es) — o texto que o IPMA também dá em `text`;
   - o equivalente ao `reportDate` do IPMA = `sent` (é o que a reconciliação usa para
     "snapshot mais velho que o guardado" e precedência).

| AEMET | `awarenessTypeName` |
|---|---|
| VI Vientos | Vento |
| PR Lluvias | Precipitação |
| NE Nevadas | Neve |
| TO Tormentas | Trovoada |
| AT Temperaturas máximas | Tempo Quente |
| BT Temperaturas mínimas | Tempo Frio |
| CO Costeros | Agitação Marítima |
| NI Nieblas | Nevoeiro |
| GA Galernas, RI Rissagas, VS Polvo en suspensión, DH Deshielos, AL Aludes, outros | nome espanhol (`fenomeno` depois do `;`) |

### Reconciliação e histórico: o mesmo motor do IPMA

O algoritmo de `HandleWeatherWarnings::sync()` (pares por `(área, tipo)` por
sobreposição estrita, atualização no sítio, mudanças de nível, `supersededAt`,
snapshot vazio ou mais velho ignorado, `terminado`) e o
`WeatherWarningEventRecorder` (`emitido`, `agravado`, `desagravado`, `prolongado`,
`encurtado`, `texto_alterado`, `retirado`, `terminado`) passam a ser **parametrizados
pelos modelos** e usados pelas duas fontes:

- IPMA: `WeatherWarning` / `weather_warnings` + `WeatherWarningEvent` — como hoje.
- AEMET: `AemetWarning` / `aemetWarnings` + `AemetWarningEvent` /
  `aemetWarningEvents`, mesmos campos (o `district` guarda o código da zona) e mais
  `areaName`, `phenomenon` (código AEMET, ex. `VI`), `parameter`
  ("Rachas máximas: 80 km/h"), `probability`, `identifier` (a mensagem CAP de
  origem) e o bloco em inglês (`en`: `headline`, `description`, `instruction`).

A forma da refatoração (classe base, serviço com os modelos injetados, trait…) fica ao
construtor. O observer de Telegram (`WeatherWarningObserver`, STB) **não** se aplica
aos avisos AEMET.

Leitura: `WeatherWarningCollapse` também (garante que dois avisos do mesmo
`(área, tipo)` não se sobrepõem, como no IPMA).

### Job e agenda

`ProcessAemetWarnings` a cada 15 min (como `HandleWeatherWarnings`), `ShouldBeUnique`,
`$timeout` abaixo do `retry_after` (90 s) do Redis — como os jobs da Madeira.
Agendado só com `AEMET_API_KEY` definido. Comando `php artisan aemet:warnings` para
correr síncrono. Uma escrita falhada do arquivo ou do histórico é registada e não
interrompe a reconciliação (como no IPMA).

### Endpoints

- `GET v2/warnings/aemet` — igual a `v2/warnings/ipma`: array de
  `{ text, awarenessTypeName, idAreaAviso, awarenessLevelID, startTime, endTime }`
  (hora de Lisboa sem offset, ordenado por `startTime`), mais os campos extra
  `areaName`, `phenomenon`, `parameter`, `probability`. Filtro `peninsula=1`.
  Mesma cache que o do IPMA (se o do IPMA não tem, 60 s).
- `GET v2/warnings/aemet/history` — igual a `v2/warnings/ipma/history`: mesmos
  parâmetros (`from`/`to`/`area`/`type`/`kind`, default 7 dias, máx. 31,
  `texto_alterado` só a pedido), mesma forma de resposta, sobre `aemetWarningEvents`.
- `GET v2/warnings/aemet/zones` — GeoJSON FeatureCollection das zonas
  (`properties`: `code`, `name`, `ccaa`, `province`, `coastal`, `peninsula`).
  Filtro `peninsula=1`. Cache 1 h.

Controller próprio (`AemetController` ou dentro de `WarningsController`, ao
construtor), reaproveitando o código do IPMA em vez de o copiar.

## Estações

### Fontes

- Inventário: `valores/climatologicos/inventarioestaciones/todasestaciones` — 926
  estações (`indicativo`, `nombre`, `provincia`, `latitud`/`longitud` em DMS, ex.
  `"394924N"` / `"025309E"`, `altitud`).
- Observações: `observacion/convencional/todas` — **últimas 12 h**, uma linha por
  estação × hora (`fint`, UTC), ~850 estações, ~10 500 linhas. Cada linha traz
  `idema`, `ubi`, `lat`, `lon`, `alt` e só os campos que a estação mede.
- Diários: `valores/climatologicos/diarios/datos/fechaini/{AAAA-MM-DDTHH:MM:SSUTC}/fechafin/{…}/todasestaciones`
  — um registo por estação × dia, publicados com **~3 dias de atraso** (a 13/11 o mais
  recente era 09/11), ~820 estações/dia. Números como strings com **vírgula
  decimal** (`"17,6"`); `prec` pode ser `"Ip"` (precipitação inapreciável → 0.0);
  `horatmax` pode ser `"Varias"`; `dir` em dezenas de graus (`"16"` = 160°; `99` ou
  `88` = sem direção/variável → null); `velmedia`/`racha` em m/s.

### Normalização para os campos do IPMA

Guarda-se já com os nomes e unidades do IPMA (mais o registo original em `raw`), para
os endpoints serem uma cópia dos do IPMA.

Observações horárias (`AemetObservation`, `aemetObservations`, upsert por
`(stationId, date)`, `date` = `fint` UTC):

| IPMA | Unidade | AEMET |
|---|---|---|
| `temperatura` | °C | `ta` |
| `humidade` | % | `hr` |
| `pressao` | hPa | `pres` (pressão à estação, como no IPMA) |
| `precAcumulada` | mm na hora | `prec` |
| `intensidadeVento` | m/s | `vv` |
| `intensidadeVentoKM` | km/h | `vv` × 3.6 (1 casa decimal) |
| `idDireccVento` | 0 calmo, 1 N, 2 NE … 8 NO | `dv` em graus → sector de 45° centrado (N = 337,5–22,5); `vv` = 0 → 0 |
| `radiacao` | kJ/m² | não existe na AEMET → em falta |

Resumos diários (`AemetDataDaily`, `aemetDataDaily`, upsert por `(stationId, date)`,
`date` = dia civil), nos `DAILY_FIELDS` do IPMA:

| IPMA | AEMET |
|---|---|
| `temp_min` / `temp_med` / `temp_max` | `tmin` / `tmed` / `tmax` |
| `hum_min` / `hum_med` / `hum_max` | `hrMin` / `hrMedia` / `hrMax` |
| `prec_quant` | `prec` |
| `vento_int_med` / `vento_int_max_inst` | `velmedia` / `racha` (m/s — o construtor confirma a unidade guardada pelo IPMA em `UpdateWeatherDataDaily` e converte se for outra) |
| `vento_dir_max` | `dir` × 10 (graus) |
| `idDireccVento` | sector de `vento_dir_max`, como acima |
| `pressao` | média de `presMax` e `presMin` quando ambos existem |
| `prec_max_inst`, `rad_total` | sem equivalente → em falta |

Valores em falta seguem a convenção de cada endpoint do IPMA: `-99` em
`v2/weather/observations` (é o que o IPMA devolve aí) e `null` nos endpoints por
estação.

### Estações

`AemetStation` (`aemetStations`): `stationId` (= `idema`, string, único),
`location` (nome — mesmo papel do `location` do IPMA), `place` (província),
`coordinates` (`[lng, lat]`, como o IPMA), `altitude`, `peninsula` (pela província:
Illes Balears, Las Palmas, Santa Cruz de Tenerife, Ceuta, Melilla → false),
`lastSeenAt`, índice geo. Atualizado do inventário (DMS → decimal); uma estação que
apareça nas observações sem estar no inventário é criada com `ubi`/`lat`/`lon`.

Retenção: guardar tudo (como as do IPMA). Ordem de grandeza: ~850 × 24 × 365 ≈ 7,5 M
observações/ano + ~300 k diários/ano.

### Jobs

- `UpdateAemetStations` — diário (inventário).
- `UpdateAemetObservations` — de hora a hora (o feed cobre 12 h, falhas isoladas não
  deixam buracos).
- `UpdateAemetDataDaily` — diário, pede os últimos 10 dias (cobre o atraso e
  republicações; upsert).
- Todos `ShouldBeUnique`, timeout abaixo do `retry_after`; comandos `php artisan
  aemet:stations|observations|daily` para correr síncrono.

### Endpoints

- `GET v2/weather/aemet/stations` — igual a `v2/weather/stations/ipma`: array de
  Features `{ geometry: {type: Point, coordinates}, type: Feature, properties:
  { idEstacao, localEstacao } }` (`idEstacao` string), mesma cache (1 h). Filtro
  `peninsula=1`.
- `GET v2/weather/aemet/observations` — igual a `v2/weather/observations`: últimas
  24 h, `{ "<UTC Y-m-dTH:i>": { "<idEstacao>": { intensidadeVentoKM, temperatura,
  radiacao, idDireccVento, precAcumulada, intensidadeVento, humidade, pressao } } }`,
  `-99` em falta, mesma cache (10 min).
- `GET v2/weather/aemet/stations/{id}/observations?hours=` — igual ao do IPMA
  (1–168, default 48, 422 `invalid_hours`, 404 estação desconhecida): `{ stationId,
  location, place, coordinates, observations: [{ date (UTC, Z), …campos }] }`.
- `GET v2/weather/aemet/stations/{id}/daily?days=` — igual ao do IPMA (1–90, default
  30, 422 `invalid_days`): `{ stationId, location, place, coordinates, daily:
  [{ date (dia civil), …DAILY_FIELDS }] }`. O dia civil é o de Espanha peninsular
  (a AEMET agrega por dia local); documentar.

Documentação OpenAPI em `docs/warnings/paths/` e `docs/weather/paths/` (pode
referenciar os esquemas do IPMA) + referência em `docs/api.yaml`.

## Saúde

`App\Support\AemetFeedHealth`, no molde de `MadeiraFeedHealth`: fontes `warnings`,
`observations`, `daily`; uma mensagem no canal de erros do Discord
(`DiscordTool::postError`) quando uma fonte falha há mais de `AEMET_STALE_MINUTES`
(default 60; para `daily`, 48 h), outra quando recupera. Um snapshot de avisos
ignorado (vazio/velho) conta como falha.

## Configuração

`.env` / `.env.example`: `AEMET_API_KEY` (vazio → jobs AEMET desligados),
`AEMET_STALE_MINUTES` (60). Lidos via `config/services.php` (`services.aemet.*`),
não `env()` direto no código.

## Testes

Fixtures reais reduzidas em `tests/fixtures/aemet/` (um tar pequeno com mensagens
`verde`, `amarillo`, `naranja`, uma `Update` que referencia outra, uma expirada, uma
com várias áreas e uma área com 2 polígonos; JSON de observações e de diários em
ISO-8859-15 com acentos, vírgulas decimais, `Ip`, `Varias`; um inventário).
HTTP falso conforme o padrão dos jobs vizinhos.

Cobrir: dois passos + falhas (vazio, 401, 429, `estado` ≠ 200); CAP → itens no formato
IPMA (substituição por `references`, multi-área, verde fora, tipos sem equivalente);
reconciliação e eventos AEMET a passarem pelos mesmos casos que os do IPMA; **os
testes do IPMA existentes passam sem alterações**; mensagens arquivadas uma só vez;
zonas (MultiPolygon lon/lat, `peninsula`); DMS; encoding; conversões de unidades e
direção; e, para cada par da tabela de correspondência, um teste que compara a
**forma** da resposta AEMET com a do IPMA (mesmas chaves, mesmos tipos exceto o id).

## Deixado ao construtor

- Nomes exatos de classes/comandos/coleções e a forma da refatoração que partilha a
  reconciliação, o registo de eventos e os controllers com o IPMA.
- Parser CAP (SimpleXML/DOM).
- Se o `raw` fica no documento ou noutro sítio, desde que nada se perca.

## Deploy

Sem dependências novas. Passos: `git pull`, migrations (índices),
`AEMET_API_KEY` no `.env` de produção, reload do php-fpm, `queue:restart`, confirmar
no scheduler. Primeira importação manual: `aemet:stations`, `aemet:observations`,
`aemet:daily`, `aemet:warnings`.
