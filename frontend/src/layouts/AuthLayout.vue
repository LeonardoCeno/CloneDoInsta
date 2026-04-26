<template>
  <div class="auth-shell">
    <div class="auth-shell__panel card border-0">
      <div class="auth-shell__brand">
        <span class="auth-shell__wordmark">Manya</span>
        <p>Acesse sua conta ou crie um novo perfil.</p>
      </div>

      <RouterView />
    </div>
  </div>
</template>

<style scoped>
.auth-shell {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 1.5rem;
  background:
    radial-gradient(circle at top, rgba(0, 149, 246, 0.12), transparent 32%),
    var(--app-bg);
}

.auth-shell__panel {
  width: min(100%, 28rem);
  padding: 2rem;
  border-radius: 1.35rem;
  background: var(--app-surface);
}

.auth-shell__brand {
  display: grid;
  gap: 0.35rem;
  margin-bottom: 1.5rem;
  text-align: center;
}

.auth-shell__wordmark {
  font-family: 'Grand Hotel', cursive;
  font-size: 3rem;
  line-height: 0.95;
}

.auth-shell__brand p {
  margin: 0;
  color: var(--app-muted);
  line-height: 1.6;
}
</style>
