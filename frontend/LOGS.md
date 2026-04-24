## 2026-04-20 - Setup do Projeto (Item 1)

### Escopo executado
- Leitura de [README.md](README.md) e [TASKS.md](TASKS.md) para alinhar o roadmap.
- Instalação de dependência de roteamento: `vue-router@4`.
- Estrutura inicial criada para organizar o frontend por domínio:
	- `src/router`
	- `src/layouts`
	- `src/views/auth`
	- `src/views/app`
	- `src/assets/styles`
- Configuração de rotas do frontend com páginas base e fallback 404.
- Tema global definido com variáveis de cor, tipografia, espaçamentos e layout responsivo (mobile-first).
- App inicial conectado ao Router e ao CSS global.

### Validação
- Build executado com sucesso (`npm run build`).

### Status do item 1 (TASKS)
- [x] Inicializar projeto (Vue)
- [x] Configurar estrutura de pastas
- [x] Configurar rotas do frontend (Vue Router)
- [x] Definir tema global (cores, fontes, espaçamentos no estilo Instagram)
- [ ] Configurar serviço HTTP (Axios) com interceptors pra JWT

### Observação
- Parte HTTP/Axios mantida pendente por decisão do projeto até a API estar pronta.

## 2026-04-20 - Autenticacao (Item 2)

### Escopo executado
- Implementacao de autenticacao local (sem HTTP) via composable global em `src/composables/useAuth.js`.
- Persistencia de sessao com `localStorage`:
	- Token em `instaclone.token`
	- Usuario autenticado em `instaclone.user`
	- Contas locais para teste em `instaclone.accounts`
- Tela de Login conectada com estado reativo, validacao de campos, mensagens de erro e redirecionamento pos-login.
- Tela de Cadastro conectada com estado reativo, validacoes (campos obrigatorios, formato de username, confirmacao de senha) e login automatico apos cadastro.
- Guard de rotas protegidas implementado no Vue Router:
	- Rotas do app exigem autenticacao
	- Rotas de visitante (`/login`, `/cadastro`) redirecionam para feed quando usuario ja esta logado
	- Usuario deslogado tentando acessar rota protegida e enviado para login com `redirect`.
- Acao de logout adicionada no layout principal, limpando sessao e retornando para login.

### Validacao
- Build executado com sucesso (`npm run build`).

### Status do item 2 (TASKS)
- [x] Tela de Login
- [x] Tela de Cadastro
- [x] Logica de salvar/remover token no localStorage
- [x] Redirecionamento automatico (logado -> feed, deslogado -> login)
- [x] Guard de rotas protegidas
- [SKIP] Configurar interceptor JWT no Axios

### Observacao
- Fluxo atual simula autenticacao apenas no frontend para permitir desenvolvimento enquanto a API nao estiver disponivel.
