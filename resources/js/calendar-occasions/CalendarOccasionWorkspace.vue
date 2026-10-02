<template>
    <main class="calendar-occasion-workspace" aria-labelledby="calendar-occasion-title">
        <p class="calendar-occasion-workspace__eyebrow">Tabelas centrais</p>
        <h2 id="calendar-occasion-title">Gestão de feriados</h2>
        <p>O cadastro e a consulta de definições globais de feriados serão realizados nesta área.</p>
    </main>
</template>

<style scoped>
.calendar-occasion-workspace { display: grid; align-content: start; gap: var(--space-3); max-width: 72rem; margin: 0 auto; padding: var(--space-4); }
.calendar-occasion-workspace__eyebrow { margin: 0; color: var(--color-text-secondary); font-size: var(--font-size-sm); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
.calendar-occasion-workspace h2, .calendar-occasion-workspace p { margin: 0; }
.calendar-occasion-workspace h2 { color: var(--color-text-primary); font-family: var(--font-family-display); font-size: var(--font-size-xl); }
.calendar-occasion-workspace > p:last-child { color: var(--color-text-secondary); }
</style>
