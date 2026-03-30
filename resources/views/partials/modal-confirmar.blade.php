<div class="modal fade" id="modal-confirmar" tabindex="-1" role="dialog" aria-labelledby="confirmar-titulo">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" id="confirmar-header">
                <h5 class="modal-title" id="confirmar-titulo">Confirmar</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form-confirmar" method="POST" data-loading-msg="Aguarde...">
                @csrf
                <input type="hidden" name="_method" id="confirmar-method" value="DELETE">
                <div class="modal-body">
                    <p id="confirmar-mensagem" class="mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn" id="confirmar-btn">
                        Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
