function confirmar(mensagem, textoBotao = 'Sim, cancelar') {
  return new Promise(resolve => {
    const fundo = document.createElement('div');
    fundo.className = 'modal-fundo';
    fundo.innerHTML = `
      <div class="modal" role="dialog" aria-modal="true">
        <h2>Tem certeza?</h2>
        <p>${mensagem}</p>
        <div class="acoes">
          <button class="btn sec" data-r="0">Não, voltar</button>
          <button class="btn perigo" data-r="1">${textoBotao}</button>
        </div>
      </div>`;
    fundo.addEventListener('click', e => {
      const b = e.target.closest('button');
      if (b || e.target === fundo) { fundo.remove(); resolve(b?.dataset.r === '1'); }
    });
    document.body.appendChild(fundo);
    fundo.querySelector('button').focus();
  });
}