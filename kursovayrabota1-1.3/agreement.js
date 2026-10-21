'use strict';

(function () {
    const openBtns   = document.querySelectorAll('[data-agreement-open]');
    const modal      = document.getElementById('agreementModal');
    if (!modal) return;

    const pages      = modal.querySelectorAll('.agreement-page');
    const pageNum    = modal.querySelector('.agreement-page-num');
    const prevBtn    = modal.querySelector('[data-agreement-prev]');
    const nextBtn    = modal.querySelector('[data-agreement-next]');
    const finishBtn  = modal.querySelector('[data-agreement-finish]');
    const closeBtns  = modal.querySelectorAll('[data-agreement-close]');
    const agreeCb    = document.getElementById('agree_terms');
    const statusEl   = document.getElementById('agreement-status');

    const total = pages.length;
    let current = 0;
    const readPages = new Set();

    function render() {
        pages.forEach((p, i) => {
            p.hidden = (i !== current);
        });
        if (pageNum) pageNum.textContent = (current + 1) + ' / ' + total;

        prevBtn.disabled = (current === 0);

        readPages.add(current);

        const isLast = (current === total - 1);
        nextBtn.hidden = isLast;
        finishBtn.hidden = !isLast;
    }

    function openModal() {
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        current = 0;
        readPages.clear();
        render();
    }

    function closeModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function enableAgreeCheckbox() {
        if (!agreeCb) return;
        agreeCb.disabled = false;
        if (statusEl) {
            statusEl.textContent = '✅ Соглашение прочитано. Поставь галочку — регистрация разблокирована.';
            statusEl.className = 'agreement-status ok';
        }
        closeModal();
        agreeCb.focus();
    }

    openBtns.forEach(btn => btn.addEventListener('click', function (e) {
        e.preventDefault();
        openModal();
    }));

    closeBtns.forEach(btn => btn.addEventListener('click', closeModal));
    
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });

    prevBtn.addEventListener('click', function () {
        if (current > 0) { 
            current--; 
            render(); 
        }
    });
    
    nextBtn.addEventListener('click', function () {
        if (current < total - 1) { 
            current++; 
            render(); 
        }
    });
    
    finishBtn.addEventListener('click', enableAgreeCheckbox);

    if (agreeCb && agreeCb.disabled) {
        agreeCb.disabled = true;
    }
})();