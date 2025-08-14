function showModal(selector) {
  var modal = document.querySelector(selector);
  if (!modal) return;
  modal.style.display = 'block';
  modal.classList.add('show');
  modal.removeAttribute('aria-hidden');
  document.body.classList.add('modal-open');
  var backdrop = document.createElement('div');
  backdrop.className = 'modal-backdrop fade show';
  document.body.appendChild(backdrop);
}

function hideModal(selector) {
  var modal = document.querySelector(selector);
  if (!modal) return;
  modal.classList.remove('show');
  modal.style.display = 'none';
  modal.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('modal-open');
  var backdrop = document.querySelector('.modal-backdrop');
  if (backdrop) backdrop.parentNode.removeChild(backdrop);
}

document.addEventListener('click', function (e) {
  var trigger = e.target.closest('[data-dismiss="modal"]');
  if (trigger) {
    var modal = trigger.closest('.modal');
    if (modal) hideModal('#' + modal.id);
  }
});
