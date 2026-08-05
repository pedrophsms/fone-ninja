# ADR-0001: shadcn-vue no lugar de Vuetify

O frontend era construído sobre Vuetify 3 (Material Design 3 default, sem tema custom). Para a direção visual "editorial limpo" aprovada (hairline rules, grid generoso, ledgers de inventário), o sistema de tokens MD3 do Vuetify (elevação, rounded, densidade Material) lutava contra a estética; personalizá-lo até o nível de uma identidade própria seria mais trabalho que trocar de sistema. Decidimos substituir Vuetify + @mdi/font por **shadcn-vue** (Tailwind CSS v4 + Reka UI/Radix Vue + componentes copiados para o repo) + `@tanstack/vue-table` para as tabelas-ledger.

Razões:
- **Controle total dos tokens**: shadcn usa variáveis CSS para `background`, `foreground`, `primary`, `border`, etc. — a identidade (cool paper + tinta verde-ledger + latão, light/dark) nasce nos tokens, não como override de um tema MD3.
- **Estética editorial**: sem elevação Material, sem cards arredondados pesados — o look hairline/ledger é o padrão, não a exceção.
- **Copy-paste components no repo**: cada componente é código nosso, estilizável sem fighting o framework.

Custo aceito:
- Reescrever toda a UI (App shell, 4 telas + dashboard novo) em Tailwind + primitivos shadcn.
- Specs de componente que montavam Vuetify (`SalesView.spec`, `PurchasesView.spec`) precisam de rework; jsdom ganha polyfills (matchMedia, PointerEvent) para Radix.
- Perder os componentes ricos prontos (ex: `v-data-table`) — compensado por TanStack Table.
- MDI sai, entra `lucide-vue-next`.

Decisões mantidas de propósito: camadas (View → composable → store → service), validação client-side nas composables (sem zod/vee-validate), idempotência por `idempotencyKey`, snackbar global, padrão PT-BR no domínio.
