<template>
  <div class="app-shell">
    <div class="app-shell__inner">
      <aside v-if="$slots.sidebar" class="app-shell__sidebar">
        <slot name="sidebar" />
      </aside>

      <div class="app-shell__content">
        <header v-if="$slots.header" class="app-shell__header">
          <slot name="header" />
        </header>

        <main class="app-shell__main">
          <slot />
        </main>

        <footer v-if="$slots.footer" class="app-shell__footer">
          <slot name="footer" />
        </footer>
      </div>
    </div>
  </div>
</template>
