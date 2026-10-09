# Backend — João Domingues

API Laravel 12 do site e do painel. O painel que já existe em `admin/` é a área administrativa: este projeto não cria um segundo ecrã. A landing em `web/` mantém o visual; com `window.API_URL` passa a ler imóveis, textos e redes da API e a gravar os pedidos de contacto.

Não há depoimentos nem perguntas frequentes na landing publicada. Esses módulos não foram criados.

## O que a API cobre

| No site / painel | Na API |
| --- | --- |
| Cards e ficha do imóvel (`web/js/imoveis.js`) | `GET /api/v1/imoveis` e `GET /api/v1/imoveis/{id}` |
| Textos PT/EN/FR/ES (`web/js/idiomas.js`) | `GET /api/v1/textos` |
| Redes do rodapé (`web/js/redes.js`) | `GET /api/v1/definicoes` |
| Formulário "Quero vender" (`web/js/pedidos.js`) | `POST /api/v1/pedidos` |
| Painel: imóveis, textos, formulários, utilizadores | os mesmos caminhos, com token |

A listagem do painel espera um **array JSON**, não o formato `{ data, links, meta }` do Laravel. A paginação (`?paginar=1`) existe para integrações; o painel não a usa.

## Arquitectura

- Laravel 12, PHP 8.2, MySQL ou MariaDB, Eloquent.
- API em `/api/v1`. Autenticação do painel com **Laravel Passport**: o login valida o e-mail e a senha e devolve um token de acesso pessoal. As rotas protegidas validam esse token no guarda `api` do Passport (`Authorization: Bearer`). Não há cookies de sessão na API, por isso o CSRF do browser não se aplica a estes pedidos.
- Perfis iguais aos do painel: `administrador` (tudo) e `editor` (dashboard, imóveis, textos, pedidos). `permissoes` no utilizador, se estiver preenchido, substitui o perfil.
- Imóveis e pedidos usam exclusão lógica. Um imóvel apagado no painel deixa de aparecer, mas o contacto que o referia conserva o `imovel_id`.
- O pedido é gravado **antes** do e-mail. O envio corre num job (`NotificarNovoPedido`). Se o SMTP falhar, o contacto fica na base com `email_estado=falhou` e o erro vai para o log, sem o nome do visitante.
- O remetente é `MAIL_FROM_ADDRESS`. O e-mail do visitante vai em `Reply-To`. O destinatário é `LEADS_NOTIFICATION_EMAIL` (por omissão `jmdomingues@remax.pt`).

## Requisitos

- PHP 8.2 com `mbstring`, `xml`, `curl`, `zip`, `pdo_mysql`, `intl`, `bcmath`, `fileinfo` e `gd`.
- Composer 2.
- MySQL 8 ou MariaDB.
- Para os testes automatizados: extensão `pdo_sqlite` (o PHPUnit usa SQLite em memória).

## Instalação

Neste repositório (`JoaoDomingues-backend`, separado do site):

```bash
php8.2 /usr/local/bin/composer install
cp .env.example .env
php8.2 artisan key:generate
```

Crie a base e um utilizador MySQL. No `.env`:

```dotenv
DB_DATABASE=joao_domingues
DB_USERNAME=...
DB_PASSWORD=...
```

```bash
php8.2 artisan migrate
php8.2 artisan passport:preparar
php8.2 artisan db:seed
php8.2 artisan storage:link
php8.2 artisan admin:criar --nome="João Domingues" --email="jmdomingues@remax.pt" --senha="uma-senha-longa"
php8.2 artisan serve
```

Com `php artisan serve`, a API fica em `http://localhost:8000`. No Docker, fica em `http://localhost:8048`. O painel e o site usam esse endereço com `/api/v1` no fim.

## Docker

O `Dockerfile` e o `docker-compose.yml` seguem o da API do Arquiteto Online, reduzidos ao que esta API usa: PHP 8.3, MySQL 8.4 e a fila na base de dados. Não há Octane, RoadRunner nem Redis.

Local (sobe um MySQL próprio, publica as portas 8048 e 3348 e usa http):

```bash
cp .env.example .env
# Preencha APP_KEY (php artisan key:generate --show) e DB_PASSWORD.
docker compose -f docker-compose.yml -f docker-compose.local.yml up -d --build
```

O contentor corre as migrations e o `passport:preparar` ao arrancar e deixa um `queue:work` em segundo plano para o e-mail dos leads. Na primeira vez, com a base vazia, carrega os textos do site (PT, EN, FR, ES) e os contactos/redes. Em todos os arranques corre o `UtilizadoresSeeder`, que cria os 3 utilizadores do painel que ainda não existam (a senha vem de `SEED_SENHA_*`; sem ela, é gerada e mostrada uma vez no log). O comando `admin:criar` continua disponível para contas extra.

Volumes: `joao-mysql` (base), `joao-storage-public` (fotos dos imóveis) e `joao-passport` (chaves do Passport; sem ele, cada deploy obrigaria a entrar de novo).

### Produção (Dokploy)

A API fica em `https://apijoaodomingues.guilhermeviana.com`.

1. Crie um serviço **Docker Compose** a apontar para este repositório (`docker-compose.yml`, sem o `.local`).
2. Em **Domains**: `apijoaodomingues.guilhermeviana.com`, serviço `api`, porta `8048`, HTTPS com Let's Encrypt.
3. Em **Advanced > Volumes/Mounts**, um *Volume Mount* para cada pasta (também declaradas com `VOLUME` no `Dockerfile`):

| Volume Name | Mount Path | Conteúdo |
|---|---|---|
| `joao-storage-public` | `/var/www/html/storage/app/public` | fotos dos imóveis |
| `joao-passport` | `/var/www/html/storage/oauth` | chaves do Passport |

   Sem eles, cada deploy apaga as fotos e obriga toda a gente a entrar de novo.
4. Em **Environment**:

```env
APP_NAME="Joao Domingues Imobiliario"
APP_KEY=base64:...                 # php artisan key:generate --show
APP_ENV=production
APP_DEBUG=false
APP_URL=https://apijoaodomingues.guilhermeviana.com
APP_TIMEZONE=Europe/Lisbon
APP_LOCALE=pt
LOG_CHANNEL=stderr
DATABASE_URL=mysql://utilizador:senha@host-interno-do-mysql-no-dokploy:3306/base
FRONTEND_URL=https://joaodomingues.vercel.app
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
MAIL_MAILER=smtp                   # e MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS
LEADS_NOTIFICATION_EMAIL=jmdomingues@remax.pt
SEED_SENHA_ADMIN=...
SEED_SENHA_SUPORTE=...
SEED_SENHA_EDITOR=...
```

O HTTPS termina no Traefik do Dokploy; a API confia nos cabeçalhos `X-Forwarded-*` (`trustProxies`), por isso as URLs das fotos saem em `https://`. Nenhuma porta é publicada no host. A base é o MySQL criado no Dokploy: copie o *Internal Connection URL* para `DATABASE_URL` (o compose de produção não sobe MySQL; o `docker-compose.local.yml` sobe um para a máquina local).

Enquanto a base não tiver imóveis publicados, o site continua a mostrar os de `web/js/imoveis-dados.js`.

## Ligar o painel e o site

`admin/.env.local`:

```dotenv
VITE_API_URL=http://localhost:8048/api/v1
```

Reinicie o `npm run dev`. Sem esta variável o painel continua na API simulada do browser.

`web/js/config.js`:

```javascript
window.API_URL = "http://localhost:8048/api/v1";
```

O site estático não lê variáveis da Vercel em tempo de execução. Em produção, grave este endereço no `config.js` antes do deploy (ou substitua o ficheiro no build). O painel, esse sim, recebe `VITE_API_URL` nas variáveis de ambiente da Vercel, porque o Vite embute o valor no build.

Na Vercel do **backend** (ou no servidor onde a API correr), a API não vai no mesmo projecto que a pasta `web/`. O domínio da API tem de estar em `CORS_ALLOWED_ORIGINS` e em `FRONTEND_URL`.

## Filas

`.env.example` usa `QUEUE_CONNECTION=database`. O e-mail do lead só sai quando o worker está a correr:

```bash
php8.2 artisan queue:work --tries=3
```

Em desenvolvimento pode usar `QUEUE_CONNECTION=sync`: o e-mail é tentado no próprio pedido. Uma falha continua a não apagar o contacto.

Em produção, supervisione o worker (systemd, Supervisor ou o equivalente do alojamento) e use `database` ou Redis. A tabela `jobs` é criada pela migration.

## Ficheiros

As fotos do painel vão para o disco `public` (`storage/app/public/imoveis`). `php artisan storage:link` expõe-as em `/storage/...`. O painel guarda o URL absoluto devolvido por `POST /api/v1/imagens`. JPG, PNG e WebP, até 5 MB. O tipo é validado pelo MIME, não só pela extensão.

## E-mail

Preencha no `.env` uma conta SMTP **autorizada a enviar**. O domínio do João não implica que qualquer SMTP possa usar o endereço dele como remetente.

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"
LEADS_NOTIFICATION_EMAIL=jmdomingues@remax.pt
```

Para experimentar sem servidor de correio: `MAIL_MAILER=log`. A mensagem fica em `storage/logs/laravel.log`.

Se um lead chegar ao painel com `emailEstado: "falhou"`, o SMTP recusou ou não chegou a ligar. O contacto está na base. O motivo (sem dados pessoais) está no log, à volta de `Falha ao notificar lead`.

O formulário do site continua a abrir o WhatsApp. O registo na API é em paralelo. Um campo escondido (`website`) serve de honeypot; o endpoint aceita no máximo 8 pedidos por minuto e por IP. O consentimento é obrigatório no formulário do site e fica gravado quando o browser o envia.

## Testes

```bash
php8.2 artisan test
```

Os testes usam SQLite em memória e o mailer `array` (não enviam correio real). É preciso `php8.2-sqlite3`. Simulam também a falha de SMTP: o pedido tem de continuar na base.

## Endpoints

Públicos:

| Método | Caminho | Notas |
| --- | --- | --- |
| `GET` | `/api/v1/imoveis` | Só publicados. Filtros: `q`, `estado`, `tipo`, `finalidade`, `zona`, `preco_min`, `preco_max`. `paginar=1` devolve páginas. |
| `GET` | `/api/v1/imoveis/destaque` | Publicados e marcados como destaque. |
| `GET` | `/api/v1/imoveis/{id-ou-slug}` | 404 se for rascunho. |
| `GET` | `/api/v1/textos` | `{ pt, en, fr, es }` |
| `GET` | `/api/v1/definicoes` | Nome, e-mail, telefone, WhatsApp, redes. |
| `POST` | `/api/v1/pedidos` | Também `POST /api/v1/contact`. Resposta `201` com `{ message, id }`. Não devolve a lista de contactos. |

Com `Authorization: Bearer`:

| Método | Caminho | Módulo |
| --- | --- | --- |
| `POST` | `/api/v1/auth/login` | público, corpo `{ email, senha }` → `{ token, utilizador }` |
| `POST` | `/api/v1/auth/logout` | revoga o token |
| `GET` | `/api/v1/auth/me` | sessão |
| `POST` | `/api/v1/auth/recuperar-senha` | público, `{ email }`. A resposta não diz se a conta existe. |
| `POST` | `/api/v1/auth/redefinir-senha` | público, `{ email, token, senha }`. O link do e-mail abre `/admin/#/redefinir-senha`. |
| `GET` | `/api/v1/dashboard` | totais de imóveis e leads |
| `GET` | `/api/v1/imoveis` | com token, inclui rascunhos |
| `POST` | `/api/v1/imoveis` | criar |
| `PUT` | `/api/v1/imoveis/{id}` | editar |
| `PATCH` | `/api/v1/imoveis/{id}` | `{ publicado: true/false }` |
| `DELETE` | `/api/v1/imoveis/{id}` | exclusão lógica |
| `POST` | `/api/v1/imagens` | `multipart` campo `foto` → `{ url }` |
| `PUT` | `/api/v1/textos` | gravar os quatro idiomas |
| `GET` | `/api/v1/pedidos` | `q`, `status`, `imovel_id`, `desde`, `ate`, `paginar` |
| `GET` | `/api/v1/pedidos/{id}` | detalhe |
| `PATCH` | `/api/v1/pedidos/{id}` | `{ status, observacoes? }`. Estados: `novo`, `visualizado`, `em-contacto`, `finalizado` |
| `DELETE` | `/api/v1/pedidos/{id}` | exclusão lógica |
| `GET/POST/PUT/DELETE` | `/api/v1/utilizadores` | só administrador |
| `PUT` | `/api/v1/definicoes` | só administrador |

Erros: `{ message, errors? }` com 401 (sessão), 403 (sem permissão ou conta inactiva), 404, 422 e 429.

Campos extra do imóvel, aceites pela API e ignorados pelo formulário actual do painel até haver controlos para eles: `finalidade` (`venda` ou `arrendamento`), `moeda`, `destaque`, `ordem`, morada, áreas e metadados SEO. O `destaque` sobrevive a uma gravação do painel porque o objecto é reenviado por inteiro.

## Produção

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` com `https://`.
- HTTPS no servidor da API e no site.
- `CORS_ALLOWED_ORIGINS` só com o domínio do site e, se o painel estiver noutro host, o domínio do painel.
- Credenciais apenas no `.env`. O `.env` não entra no git.
- `php artisan config:cache` e `php artisan migrate --force` no deploy.
- Worker da fila sempre ligado, se `QUEUE_CONNECTION` não for `sync`.
- Backups da base: os contactos são o histórico comercial.
- Não crie o administrador com uma senha curta. Rode `admin:criar` no servidor, com a senha por variável de ambiente local, sem a deixar no histórico do shell se o alojamento o permitir (`php artisan admin:criar` pergunta sem eco).

## Limitações

- Nesta máquina os testes precisam da extensão `pdo_sqlite`, que pode não estar instalada. O comando é `sudo apt install php8.2-sqlite3`.
- O envio real para `jmdomingues@remax.pt` depende de um SMTP autorizado. `MAIL_MAILER=log` não entrega na caixa.
- A landing não tem página própria por imóvel. O e-mail liga ao site e identifica a referência; o modal "Ver mais" continua a abrir no próprio site.
- O painel não tem um interruptor visual para "imóvel em destaque". A API grava `destaque` e expõe `GET /imoveis/destaque`.
)
