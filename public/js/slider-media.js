(function () {
    const root = document.getElementById('slider-media');
    if (!root || typeof Swiper === 'undefined') return;

    const mainEl = root.querySelector('.slider-media_main');
    const thumbsEl = root.querySelector('.slider-media__swiper-thumbs');
    if (!mainEl) return;

    const DELAY = 5000;
    const slides = mainEl.querySelectorAll('.swiper-slide');
    if (slides.length <= 1) return;

    function updateProgressBars(activeIndex) {
        root.querySelectorAll('.slider-media__swiper-thumbs .swiper-slide').forEach((slide, index) => {
            const fill = slide.querySelector('.progress-bar__fill');
            if (!fill) return;

            if (index === activeIndex) {
                fill.style.transition = 'width ' + DELAY + 'ms linear';
                fill.style.width = '100%';
            } else {
                fill.style.transition = 'none';
                fill.style.width = '0';
            }
        });
    }

    function freezeProgressBar(index) {
        const slide = root.querySelectorAll('.slider-media__swiper-thumbs .swiper-slide')[index];
        if (!slide) return;

        const fill = slide.querySelector('.progress-bar__fill');
        if (!fill) return;

        const width = window.getComputedStyle(fill).width;
        const percent = (parseFloat(width) / slide.offsetWidth) * 100;
        fill.style.transition = 'none';
        fill.style.width = percent + '%';
    }

    function updateThumbNext(thumbsSwiper, activeIndex) {
        if (!thumbsSwiper || !thumbsSwiper.slides.length) return;

        const nextIndex = (activeIndex + 1) % thumbsSwiper.slides.length;
        thumbsSwiper.slides.forEach((slide) => slide.classList.remove('thumb-next'));
        const nextSlide = thumbsSwiper.slides[nextIndex];
        if (nextSlide) nextSlide.classList.add('thumb-next');
    }

    function updateDirection(mainSwiper) {
        const el = mainSwiper.el;
        const prev = mainSwiper.previousIndex;
        const curr = mainSwiper.realIndex;

        if (curr > prev) {
            el.classList.remove('swiper_direction-back');
            el.classList.add('swiper_direction-forward');
        } else if (curr < prev) {
            el.classList.remove('swiper_direction-forward');
            el.classList.add('swiper_direction-back');
        }
    }

    let thumbsSwiper = null;

    if (thumbsEl) {
        thumbsSwiper = new Swiper(thumbsEl, {
            spaceBetween: 16,
            slidesPerView: 5,
            watchSlidesProgress: true,
            on: {
                init() {
                    updateProgressBars(0);
                },
            },
        });
    }

    const mainSwiper = new Swiper(mainEl, {
        loop: false,
        spaceBetween: 0,
        autoplay: {
            delay: DELAY,
            disableOnInteraction: false,
        },
        simulateTouch: false,
        allowTouchMove: false,
        thumbs: thumbsSwiper ? { swiper: thumbsSwiper } : undefined,
        effect: 'slide',
        speed: 1,
        on: {
            init() {
                root.classList.add('playing');
                root.classList.remove('stop');
                updateProgressBars(0);
                updateThumbNext(thumbsSwiper, 0);
            },
            slideChange() {
                updateProgressBars(this.realIndex);
                updateDirection(this);
                updateThumbNext(thumbsSwiper, this.realIndex);
            },
            autoplayStart() {
                root.classList.add('playing');
                root.classList.remove('stop');
                updateProgressBars(this.realIndex);
            },
            autoplayStop() {
                root.classList.remove('playing');
                root.classList.add('stop');
                freezeProgressBar(this.realIndex);
            },
        },
    });

    if (thumbsSwiper) {
        thumbsSwiper.on('click', function () {
            mainSwiper.autoplay.stop();
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            mainSwiper.autoplay.stop();
            freezeProgressBar(mainSwiper.realIndex);
        } else {
            mainSwiper.slideTo(0);
            mainSwiper.autoplay.start();
            updateProgressBars(mainSwiper.realIndex);
        }
    });

    window.addEventListener('resize', function () {
        mainSwiper.update();
        if (thumbsSwiper) thumbsSwiper.update();
    });
})();
