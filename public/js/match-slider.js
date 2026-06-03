document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('.match-slider');

    if (!root || typeof Swiper === 'undefined') {
        return;
    }

    var start = parseInt(root.dataset.start || '0', 10);
    var viewport = root.querySelector('.match-slider__viewport');

    if (!viewport) {
        return;
    }

    var swiper = new Swiper(viewport, {
        slidesPerView: 'auto',
        spaceBetween: 0,
        initialSlide: start,
        freeMode: false,
        watchOverflow: true,
        navigation: {
            nextEl: root.querySelector('.match-slider__nav--next'),
            prevEl: root.querySelector('.match-slider__nav--prev'),
        },
    });

    if (start > 0 && swiper.slides.length > start) {
        swiper.slideTo(start, 0);
    }
});
