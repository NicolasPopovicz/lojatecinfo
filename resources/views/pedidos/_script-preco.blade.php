<script>
(function () {
    const display    = document.getElementById('preco-display');
    const hidden     = document.getElementById('preco');
    const qtdInput   = document.getElementById('quantidade');
    const totalEl    = document.getElementById('total-exibido');

    // ---------------------------------------------------------------
    // Converte centavos (inteiro) → "R$ 1.234,56"
    // ---------------------------------------------------------------
    function centavosParaBRL(centavos) {
        return (centavos / 100).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
            minimumFractionDigits: 2,
        });
    }

    // ---------------------------------------------------------------
    // Converte "R$ 1.234,56" → "1234.56"  (formato aceito pelo servidor)
    // ---------------------------------------------------------------
    function brlParaNumero(brl) {
        return brl
            .replace(/[^\d,]/g, '')   // mantém só dígitos e vírgula
            .replace(',', '.');        // troca vírgula decimal por ponto
    }

    // ---------------------------------------------------------------
    // Atualiza o campo Total a partir dos valores atuais
    // ---------------------------------------------------------------
    function atualizarTotal() {
        const preco = parseFloat(hidden.value) || 0;
        const qtd   = parseInt(qtdInput.value)  || 0;
        totalEl.textContent = (preco * qtd).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });
    }

    // ---------------------------------------------------------------
    // Formata enquanto o usuário digita (preenche da direita, como caixa)
    // ---------------------------------------------------------------
    display.addEventListener('input', function () {
        const centavos = parseInt(this.value.replace(/\D/g, '') || '0', 10);
        this.value     = centavosParaBRL(centavos);
        hidden.value   = centavos > 0 ? (centavos / 100).toFixed(2) : '';
        atualizarTotal();
    });

    // Atualiza o total também quando a quantidade muda
    qtdInput.addEventListener('input', atualizarTotal);

    // ---------------------------------------------------------------
    // Inicialização: se há valor pré-existente (edição ou old()), preenche o display
    // ---------------------------------------------------------------
    const precoInicial = parseFloat('{{ $precoInicial }}') || 0;
    if (precoInicial > 0) {
        const centavos  = Math.round(precoInicial * 100);
        display.value   = centavosParaBRL(centavos);
        hidden.value    = precoInicial.toFixed(2);
        atualizarTotal();
    }
})();
</script>
