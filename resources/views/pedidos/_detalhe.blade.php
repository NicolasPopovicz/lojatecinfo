<dl class="row mb-0">
    <dt class="col-sm-4">Descrição</dt>
    <dd class="col-sm-8">{{ $pedido->descricao }}</dd>

    <dt class="col-sm-4">Cliente</dt>
    <dd class="col-sm-8">{{ $pedido->nomecliente }}</dd>

    <dt class="col-sm-4">Produto</dt>
    <dd class="col-sm-8">{{ $pedido->produto }}</dd>

    <dt class="col-sm-4">Preço</dt>
    <dd class="col-sm-8">R$ {{ number_format($pedido->preco, 2, ',', '.') }}</dd>

    <dt class="col-sm-4">Quantidade</dt>
    <dd class="col-sm-8">{{ $pedido->quantidade }}</dd>

    <dt class="col-sm-4">Total</dt>
    <dd class="col-sm-8"><strong class="text-success">R$ {{ number_format($pedido->total, 2, ',', '.') }}</strong></dd>

    <dt class="col-sm-4">Criado em</dt>
    <dd class="col-sm-8">{{ $pedido->created_at?->format('d/m/Y H:i') }}</dd>

    <dt class="col-sm-4">Atualizado em</dt>
    <dd class="col-sm-8">{{ $pedido->updated_at?->format('d/m/Y H:i') }}</dd>
</dl>
