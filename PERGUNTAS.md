# Perguntas Prováveis — Manya Backend

> Respostas diretas para o que é mais provável de perguntarem.

---

## Arquitetura

**Por que Laravel e não Node/Express ou outra coisa?**
Laravel é o framework PHP mais maduro do mercado — ORM robusto, migrations, validação, autorização, tudo integrado. PHP 8.4 com JIT e FrankenPHP entrega performance competitiva com Node em cenários típicos de API REST. Além disso, o ecossistema do Laravel cobre exatamente o que um backend de rede social precisa: autenticação, filas, eventos, storage — sem reinventar a roda.

---

**O que é FrankenPHP e por que não usar nginx + php-fpm?**
FrankenPHP é um servidor PHP moderno construído sobre o Caddy. Ele substitui a combinação nginx + php-fpm por um binário único com suporte nativo a HTTP/2, HTTPS automático e melhor gerenciamento de workers. Reduz a complexidade operacional: menos peças para configurar, menos pontos de falha. Em produção, os workers ficam em memória permanentemente — sem cold start por requisição.

---

**Por que API REST e não GraphQL?**
GraphQL seria over-engineering para esse contexto. O frontend tem telas bem definidas com payloads previsíveis — cada tela sabe exatamente o que precisa. REST com Resources bem modelados resolve o problema sem adicionar complexidade de schema, resolvers e N+1 explícito. Se o projeto crescer para múltiplos clientes com necessidades muito diferentes, GraphQL faria mais sentido.

---

**O que são as camadas do backend e por que existem?**
Route → define a URL e o middleware. Request → valida os dados de entrada e rejeita o que for inválido antes de qualquer lógica. Controller → coordena a operação, sem lógica de negócio. Service → onde a lógica de negócio vive — privacidade, notificações, regras. Model → representa o banco e os relacionamentos. Resource → formata o JSON de saída. A separação garante que cada camada tem uma responsabilidade, o que torna o código testável e fácil de evoluir sem quebrar outras partes.

---

## Banco de Dados

**Por que chave primária composta em likes, saves e reposts?**
Para garantir unicidade diretamente no banco, não só no código. Se dois processos tentarem criar o mesmo like ao mesmo tempo, o banco rejeita um deles — é uma constraint, não uma validação que pode ter race condition. O código usa `insertOrIgnore` — tenta inserir e ignora se já existe, sem precisar fazer SELECT antes.

---

**Como funciona o sistema de perfis privados?**
A tabela `follows` tem uma coluna `status` com dois valores: `pending` e `accepted`. Quando você segue uma conta pública, o registro já entra como `accepted`. Conta privada entra como `pending` e fica aguardando. O feed, os posts e as contagens de seguidores só consideram follows com status `accepted`. Ao aceitar, o status muda para `accepted`. Ao recusar, o registro é deletado. Quando o usuário muda de privado para público, todos os `pending` são auto-aceitos em batch.

---

**Como stories expiram sem um job agendado?**
`expires_at` é um atributo computado no model: `created_at + 24 horas`. O scope `active()` filtra stories onde `created_at > now() - 24h`. Não existe coluna de expiração no banco, não existe job que roda periodicamente para marcar como expirado. O story simplesmente deixa de aparecer quando o tempo passa. Simples, sem overhead operacional.

---

**O que é paginação por cursor e por que usá-la no feed?**
Paginação por offset (`LIMIT 20 OFFSET 40`) tem um problema: se um novo post aparecer enquanto o usuário pagina, os resultados se deslocam e itens se repetem ou somem. Cursor usa o ID ou timestamp do último item visto — `WHERE id < :last_seen_id ORDER BY id DESC`. É estável mesmo com novos itens chegando. O feed muda frequentemente, então cursor é a escolha certa. Listas estáticas como comentários e seguidores usam offset por simplicidade.

---

## Autenticação e Segurança

**Como funciona o Sanctum com tokens Bearer?**
No login, o Sanctum gera um token opaco, salva o hash dele na tabela `personal_access_tokens` e retorna o valor em texto claro uma única vez. Nas próximas requisições, o frontend envia o token no header `Authorization: Bearer {token}`. O Sanctum faz hash do token recebido e compara com o banco para autenticar. No logout, o registro é deletado — o token deixa de existir.

---

**O token expira? O que acontece quando expira com o usuário ainda logado?**
Tokens expiram em 7 dias. Quando uma requisição recebe 401, o frontend intercepta automaticamente via Axios interceptor, chama `POST /auth/refresh` — que revoga o token atual e emite um novo —, salva o novo token e repete a requisição original. O usuário não percebe nada. Há um mecanismo de singleton: se múltiplas requisições simultâneas receberem 401, só um refresh é feito — as outras aguardam a Promise do refresh ser resolvida.

---

**Como o sistema protege contra SQL Injection?**
Eloquent usa PDO com prepared statements em todas as queries — os valores são sempre separados da query, nunca interpolados. No único lugar onde há um LIKE dinâmico (busca de usuários), os caracteres especiais `%`, `_` e `\` são escapados via `addcslashes()` antes de montar o padrão.

---

**Como os uploads são protegidos?**
O nome original do arquivo é descartado — substituído por um UUID gerado no servidor. O tipo do arquivo é validado pelo conteúdo real (não pela extensão) usando as regras do Laravel. Tamanho máximo de 100MB para posts e 5MB para avatares. O arquivo vai direto para o Google Cloud Storage — nunca fica em disco local do container.

---

**O que são as Policies?**
Policies são classes que centralizam as regras de autorização. Antes de editar ou deletar um post, comentário ou story, o controller chama a policy — que verifica se o usuário autenticado é o dono do recurso. Se não for, retorna HTTP 403. Centralizar isso em policies evita verificações espalhadas pelo código e garante que nenhuma operação destrutiva passe sem verificar autorização.

---

## Observers e Notificações

**O que são Observers e por que usou aqui?**
Observers são classes que reagem a eventos dos models automaticamente — criação, atualização, deleção. O `LikeObserver` dispara quando um Like é criado e envia a notificação para o dono do post. O `CommentObserver` faz o mesmo para comentários. O `LikeService` não sabe que isso acontece — ele apenas cria o like. Isso mantém os services coesos: um service não precisa conhecer os efeitos colaterais de suas ações.

---

**Por que notificações de follow_request deletam a anterior antes de criar?**
Se o usuário A solicitar seguir B, for recusado e tentar de novo, não faz sentido ter duas notificações de solicitação do mesmo ator na caixa de B. O `NotificationService` deleta a notificação anterior do mesmo tipo e mesmo ator antes de criar a nova. Caixa limpa, sem spam.

---

## Infraestrutura

**Por que Cloud Run e não uma VM?**
Cloud Run é serverless: escala para zero quando não há tráfego (custo zero), sobe instâncias automaticamente sob demanda, e você não gerencia sistema operacional, patches ou capacidade. Para um projeto nessa fase, é infinitamente mais simples que gerenciar uma VM. A desvantagem é que o container é efêmero — por isso os arquivos ficam no GCS, não em disco local.

---

**O que é "container efêmero" e como o GCS resolve?**
Cloud Run pode criar e destruir containers a qualquer momento — num redeploy, num escalonamento, numa reinicialização. Qualquer arquivo salvo dentro do container desaparece. O Google Cloud Storage é um serviço externo, independente do container — o arquivo existe lá e sobrevive a qualquer evento no container. O backend usa o driver S3-compatível do Laravel para se comunicar com o GCS usando credenciais HMAC.

---

**Por que socket Unix para o Cloud SQL e não TCP?**
Socket Unix é uma conexão local — o tráfego não passa pela rede. É mais rápido (sem overhead de TCP/IP) e mais seguro (sem tráfego de banco exposto na rede). No Cloud SQL, o Cloud Run acessa via `/cloudsql/{instância}`. Sem IP de banco exposto, sem regras de firewall para gerenciar.

---

**O que o entrypoint.sh faz?**
Script que roda toda vez que o container inicia. Em ordem: garante que o `.env` existe, gera a `APP_KEY` se não estiver definida, fica em loop esperando o MySQL aceitar conexões (até 60 tentativas), executa as migrations automaticamente se `RUN_MIGRATIONS=true`, e por fim sobe o FrankenPHP. Zero intervenção manual no deploy.

---

**Como funciona o build Docker multistage?**
O Dockerfile tem duas etapas. A primeira usa uma imagem com Composer para instalar as dependências PHP — ela tem ferramentas de build que não são necessárias em produção. A segunda etapa copia só os arquivos necessários dessa primeira imagem para a imagem final, que fica enxuta. Resultado: imagem de produção menor, sem ferramentas de desenvolvimento.

---

## Perguntas Difíceis

**O sistema está pronto para alta escala?**
Para o volume atual, sim. Gargalos de escala futuros mais prováveis: notificações em tempo real (hoje são polling), queries de feed sem índices otimizados, e uploads síncronos (poderiam ir para uma fila). As fundações estão corretas — GCS para storage, Cloud Run para escalonamento horizontal, banco gerenciado. Evoluir para filas assíncronas e WebSockets seria o próximo passo natural.

---

**Por que não tem testes automatizados?**
O projeto foi desenvolvido com foco em velocidade de entrega e cobertura funcional completa. A arquitetura foi desenhada para ser testável — Services isolados, Policies separadas, Observers desacoplados. Adicionar testes de feature com banco em memória e testes unitários nos Services seria o próximo passo antes de um ambiente de produção com mais usuários.

---

**Como o sistema lida com concorrência? E se dois usuários derem like ao mesmo tempo?**
Os modelos de engajamento (`Like`, `Save`, `Repost`) usam `insertOrIgnore` — tenta inserir e ignora erro de chave duplicada silenciosamente. A chave composta `(user_id, post_id)` no banco garante unicidade. Se dois processos tentarem criar o mesmo like ao mesmo tempo, o banco deixa um passar e rejeita o outro — nenhum dado inconsistente é criado.

---

**O que acontece se o GCS ficar fora do ar?**
Uploads falham com erro 500 — o frontend exibe a mensagem de erro. Posts e perfis existentes ainda funcionam se os URLs estiverem em cache no browser. As imagens ficam quebradas até o GCS voltar. Mitigação futura: CDN na frente do GCS com cache de arquivos estáticos. Para o volume atual, o SLA do GCS (99.9%) é suficiente.

---

**Como a API está protegida contra acesso não autorizado externo?**
Toda rota que retorna ou modifica dados exige token Bearer válido. O CORS só aceita requisições da origem do frontend (`CORS_ALLOWED_ORIGINS`). Rate limiting bloqueia abuso de endpoints públicos. O Cloud Run em si não tem restrição de IP — mas sem token válido, nenhuma rota útil responde. A autenticação é a fronteira real de proteção.
