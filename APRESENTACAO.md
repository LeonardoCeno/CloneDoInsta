# Roteiro de Apresentação — Manya Backend
> Duração estimada: 8 minutos

---

## 1. Abertura — O que foi construído (30 segundos)

"O Manya é uma rede social completa — com feed, stories, reels, notificações, sistema de follows e perfis privados. Vou mostrar como o backend que sustenta tudo isso foi estruturado, as decisões técnicas por trás dele e os pontos onde a implementação vai além do básico."

---

## 2. Stack e Arquitetura Geral (1 minuto)

"O backend é uma API REST construída em **PHP 8.4 com Laravel 11**, servida pelo **FrankenPHP** — um servidor moderno que substitui a combinação tradicional de nginx + php-fpm por um binário único baseado em Caddy, com suporte nativo a HTTP/2 e performance superior.

O banco de dados é **MySQL 8.0**, a autenticação é feita com **Laravel Sanctum** usando tokens Bearer stateless — ou seja, não existe sessão no servidor, cada requisição se autentica por si só.

O deploy roda no **Google Cloud Run**: o backend e o frontend são containers Docker independentes, cada um com sua própria imagem no Artifact Registry. O banco fica no **Cloud SQL**, gerenciado pelo Google.

A arquitetura segue uma separação clara em camadas:
- **Route** → define a URL
- **Request** → valida os dados
- **Controller** → coordena
- **Service** → executa a lógica
- **Model** → acessa o banco
- **Resource** → formata a resposta

Nenhuma camada ultrapassa sua responsabilidade."

---

## 3. Banco de Dados — Decisões de Schema (1 minuto 30 segundos)

"O schema foi construído em 16 migrations incrementais. Quero destacar três decisões que mostram cuidado técnico.

**Primeira**: `likes`, `saves`, `reposts` e `story_views` usam **chave primária composta** de `user_id + post_id`. Isso significa que a própria estrutura do banco impede duplicatas — não é validação no código, é constraint no banco. Se dois processos tentarem criar o mesmo like simultaneamente, um deles falha silenciosamente.

**Segunda**: a tabela `follows` tem uma coluna `status` com dois valores: `pending` e `accepted`. Isso suporta o sistema de perfis privados — quando você segue uma conta privada, o follow entra como pending e só vira accepted quando o dono aceita. Toda a lógica de privacidade deriva dessa coluna.

**Terceira**: `stories` não tem coluna de expiração. O prazo de 24 horas é calculado em runtime pelo model — `expires_at` é um atributo computado que soma 24h ao `created_at`. O scope `active()` filtra automaticamente. Sem coluna extra, sem job de limpeza agendado."

---

## 4. Services — Onde a Lógica Vive (1 minuto 30 segundos)

"A camada de Services é onde o projeto se diferencia de um CRUD simples. Vou em três exemplos.

**FollowService**: quando você segue alguém, o sistema verifica se o perfil é privado. Se for, insere o follow com status `pending` e notifica o alvo. Se for público, já insere como `accepted`. Quando o dono aceita ou recusa, o service atualiza o status e deleta a notificação correspondente. Tudo em transações atômicas — sem race condition.

**PostService e privacidade**: o método `byUser()` verifica se o viewer tem permissão de ver os posts antes de retorná-los. O método `explore()` exclui posts de contas privadas que o viewer não segue. Essa lógica fica no Service, não no Controller — o que significa que qualquer parte do sistema que precisar de posts vai automaticamente respeitar a privacidade.

**NotificationService**: o método `notify()` tem três regras embutidas. Primeiro: nunca cria auto-notificação — ninguém se notifica. Segundo: se já existe uma notificação de `follow_request` do mesmo ator, deleta antes de criar a nova — sem duplicatas na caixa do usuário. Terceiro: ao aceitar ou recusar um follow, a notificação correspondente some automaticamente."

---

## 5. Observers — Eventos Desacoplados (45 segundos)

"Uma decisão de design importante foi usar **Observers** para notificações de curtidas e comentários.

Quando um Like é criado, o `LikeObserver` dispara automaticamente e cria a notificação. O `LikeService` não sabe que isso acontece — ele apenas cria o like. Isso mantém os services coesos e evita que lógica de notificação se espalhe pelo código.

O mesmo vale para comentários. O `CommentObserver` intercepta a criação e notifica o dono do post, ignorando o caso em que o comentarista é o próprio dono."

---

## 6. Segurança (1 minuto)

"Segurança não foi tratada como afterthought. Os pontos principais:

**Senhas** são armazenadas com hash bcrypt — irreversíveis mesmo com acesso direto ao banco.

**Tokens expiram em 7 dias**. Quando o token expira com o usuário ainda no site, o frontend intercepta o 401, chama o endpoint de refresh automaticamente e repete a requisição original — o usuário não percebe nada. Há um mecanismo de singleton: se múltiplas requisições simultâneas receberem 401, apenas um refresh é feito, as outras aguardam.

**Rate limiting** protege login e registro com 10 requisições por minuto por IP — mitiga brute force. Rotas autenticadas têm limite de 300 por minuto.

**Autorização por Policy** em toda operação destrutiva — editar post, deletar comentário, remover story. O sistema verifica se o usuário autenticado é o dono do recurso antes de qualquer ação.

**Uploads** nunca usam o nome original do arquivo — substituído por UUID. O MIME é validado pelo conteúdo real do arquivo, não pela extensão."

---

## 7. Deploy e Infraestrutura (45 segundos)

"O build usa **Docker multistage**: uma imagem de Composer instala as dependências PHP sem incluir ferramentas de build na imagem final. A imagem de produção é enxuta — só o que o runtime precisa.

No Cloud Run, o container inicializa com um script que: garante o `.env`, espera o banco ficar disponível, executa as migrations automaticamente e sobe o servidor. Zero intervenção manual.

A conexão com o Cloud SQL usa **socket Unix** — mais segura que TCP porque não passa pela rede, e mais rápida por eliminar overhead de handshake.

Os uploads de imagem e vídeo vão para o **Google Cloud Storage** via API S3-compatível. Container é efêmero — qualquer arquivo salvo localmente some no próximo deploy. O GCS resolve isso: o arquivo existe fora do container, sobrevive a qualquer redeploy ou escalonamento."

---

## 8. Encerramento (30 segundos)

"O que foi construído aqui não é só um backend funcional — é um backend que escala, que respeita separação de responsabilidades, que trata segurança como parte do design e que está rodando em produção em infraestrutura gerenciada.

Cada decisão tem uma razão técnica: chaves compostas no banco, observers para desacoplamento, services para encapsular privacidade, singleton no refresh de token. São escolhas que mostram que o sistema foi pensado, não apenas implementado."

---

## Dicas de Apresentação

- **Mostre o Swagger** ao falar das rotas — visual e convincente
- **Mostre o diagrama de fluxo** do BACKEND.md ao falar da arquitetura em camadas
- **Não leia o roteiro** — use como guia, fale com naturalidade
- Se perguntarem sobre algo não coberto, diga "isso pode ser evoluído adicionando X" — mostra que você entende o sistema além do que foi feito
