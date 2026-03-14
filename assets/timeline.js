(function () {
    if (window.__tcTimelinePreviewReady) {
        return;
    }
    window.__tcTimelinePreviewReady = true;

    function findPreviewLink(target) {
        while (target && target !== document) {
            if (target.classList && target.classList.contains('js-tc-preview')) {
                return target;
            }
            target = target.parentNode;
        }
        return null;
    }

    var previewLinks = document.querySelectorAll('.js-tc-preview');
    if (!previewLinks.length) {
        return;
    }

    var lightbox = document.createElement('div');
    lightbox.className = 'tc-timeline-lightbox';
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');

    var img = document.createElement('img');
    img.className = 'tc-timeline-lightbox__image';
    img.alt = 'preview';

    var closeButton = document.createElement('button');
    closeButton.className = 'tc-timeline-lightbox__close';
    closeButton.type = 'button';
    closeButton.setAttribute('aria-label', 'Close');
    closeButton.textContent = '脳';

    lightbox.appendChild(closeButton);
    lightbox.appendChild(img);
    document.body.appendChild(lightbox);

    function closeLightbox() {
        lightbox.classList.remove('is-open');
        img.removeAttribute('src');
        document.body.classList.remove('tc-timeline-lightbox-open');
    }

    function openLightbox(src, altText) {
        img.src = src;
        img.alt = altText || 'preview';
        lightbox.classList.add('is-open');
        document.body.classList.add('tc-timeline-lightbox-open');
    }

    document.addEventListener('click', function (event) {
        var trigger = findPreviewLink(event.target);
        if (!trigger) {
            return;
        }

        var src = trigger.getAttribute('href') || '';
        if (!src) {
            return;
        }

        event.preventDefault();
        var targetImg = trigger.querySelector('img');
        var altText = targetImg ? targetImg.getAttribute('alt') : '';
        openLightbox(src, altText);
    });

    closeButton.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', function (event) {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && lightbox.classList.contains('is-open')) {
            closeLightbox();
        }
    });
})();

