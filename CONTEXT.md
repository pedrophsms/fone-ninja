# Fone Ninja — ERP de Estoque

Single-context inventory ERP. A small retailer registers products, buys stock from suppliers, sells to customers, and tracks profit per sale. Backend is a Laravel API; frontend is a Vue 3 SPA.

## Language

**Produto**:
A sellable inventory item, identified by nome and carrying preco_venda, custo_medio and estoque.
_Avoid_: item, mercadoria, article

**Estoque**:
The current quantity of a Produto available for sale. Changes only via Compra (+), Venda (−) or Cancelamento de venda (+).
_Avoid_: inventory, stock level

**Compra**:
A stock-in transaction placed with a Fornecedor, composed of one or more line items (Produto + quantidade + preco_unitario). Raises estoque and recalculates custo_medio.
_Avoid_: entrada, purchase order

**Venda**:
A stock-out transaction made to a Cliente, composed of line items. Lowers estoque and generates lucro. Reversible via Cancelamento de venda.
_Avoid_: saída, order

**Cancelamento de venda**:
Reversing a Venda, restoring its estoque. A Venda's status becomes `cancelled`.
_Avoid_: reembolso, refund, estorno

**Fornecedor**:
The party a Compra is placed with.

**Cliente**:
The party a Venda is made to.
_Avoid_: buyer

**Custo médio**:
The per-unit cost of a Produto, recalculated on each Compra as a weighted average of existing stock and newly bought units.
_Avoid_: custo médio ponderado, avg cost

**Preço de venda**:
The per-unit price a Produto is sold at.

**Lucro**:
On a Venda, (preço de venda − custo médio) × quantidade, summed across line items.
_Avoid_: ganho, margem, profit

**Preview de venda**:
A dry-run computation of total and lucro for a prospective Venda, without mutating estoque.
_Avoid_: simulação, estimate

**Conta**:
A person who can log in, identified by nome, email and senha. The register flow creates one.
_Avoid_: usuário, registro

**Lucro acumulado**:
Total lucro across all non-cancelled Vendas.
_Avoid_: lucro total, profit margin

**Receita**:
Sum of total across all non-cancelled Vendas.

**Ticket médio**:
Receita divided by the number of non-cancelled Vendas.

**Valor em estoque**:
Sum over Produtos of (custo médio × estoque).
_Avoid_: valor de inventário, stock value

**Estoque baixo**:
A Produto whose estoque is at or below the low-stock threshold (default 10 units).
_Avoid_: out of stock, sem estoque
