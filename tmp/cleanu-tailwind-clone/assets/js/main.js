(() => {
  'use strict';

  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

  const body = document.body;
  const overlay = $('#page-overlay');
  const drawer = $('#side-drawer');
  const searchPanel = $('#search-panel');
  const videoModal = $('#video-modal');
  const videoFrame = $('#video-frame');
  const toast = $('#toast');
  let overlayOwner = null;

  const lockBody = () => body.classList.add('overflow-hidden');
  const unlockBody = () => body.classList.remove('overflow-hidden');

  function showOverlay(owner) {
    overlayOwner = owner;
    overlay.classList.remove('pointer-events-none', 'opacity-0');
    lockBody();
  }

  function hideOverlay(owner) {
    if (owner && overlayOwner !== owner) return;
    overlayOwner = null;
    overlay.classList.add('pointer-events-none', 'opacity-0');
    unlockBody();
  }

  function showToast(message) {
    toast.textContent = message;
    toast.classList.remove('translate-y-8', 'opacity-0');
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => {
      toast.classList.add('translate-y-8', 'opacity-0');
    }, 3200);
  }

  // Preloader with a timeout fallback for slow or blocked external assets.
  let preloaderRemoved = false;
  function removePreloader() {
    if (preloaderRemoved) return;
    preloaderRemoved = true;
    const preloader = $('#preloader');
    if (!preloader) return;
    preloader.classList.add('opacity-0', 'pointer-events-none');
    window.setTimeout(() => preloader.remove(), 550);
  }
  window.addEventListener('load', removePreloader);
  window.setTimeout(removePreloader, 2200);

  // Drawer
  function openDrawer() {
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    showOverlay('drawer');
  }
  function closeDrawer() {
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    hideOverlay('drawer');
  }
  $('#drawer-open')?.addEventListener('click', openDrawer);
  $$('.drawer-close').forEach((button) => button.addEventListener('click', closeDrawer));

  // Search
  function openSearch() {
    searchPanel.classList.remove('-translate-y-full');
    searchPanel.setAttribute('aria-hidden', 'false');
    showOverlay('search');
    window.setTimeout(() => $('#site-search')?.focus(), 250);
  }
  function closeSearch() {
    searchPanel.classList.add('-translate-y-full');
    searchPanel.setAttribute('aria-hidden', 'true');
    hideOverlay('search');
  }
  $('#search-open')?.addEventListener('click', openSearch);
  $('#search-close')?.addEventListener('click', closeSearch);
  $('#site-search')?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      const term = event.currentTarget.value.trim();
      if (term) showToast(`Search demo: “${term}”`);
    }
  });

  // Video modal
  function openVideo(url) {
    videoFrame.src = url;
    videoModal.classList.remove('pointer-events-none', 'opacity-0');
    $('[data-video-dialog]', videoModal)?.classList.remove('translate-y-5');
    videoModal.setAttribute('aria-hidden', 'false');
    showOverlay('video');
  }
  function closeVideo() {
    videoModal.classList.add('pointer-events-none', 'opacity-0');
    $('[data-video-dialog]', videoModal)?.classList.add('translate-y-5');
    videoModal.setAttribute('aria-hidden', 'true');
    videoFrame.src = 'about:blank';
    hideOverlay('video');
  }
  $$('.video-open').forEach((button) => button.addEventListener('click', () => openVideo(button.dataset.video)));
  $('#video-close')?.addEventListener('click', closeVideo);

  // Shared overlay close
  overlay?.addEventListener('click', () => {
    if (overlayOwner === 'drawer') closeDrawer();
    if (overlayOwner === 'search') closeSearch();
    if (overlayOwner === 'video') closeVideo();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (overlayOwner === 'drawer') closeDrawer();
    if (overlayOwner === 'search') closeSearch();
    if (overlayOwner === 'video') closeVideo();
  });

  // Mobile menu
  const mobileMenuButton = $('#mobile-menu-open');
  const mobileMenu = $('#mobile-menu');
  mobileMenuButton?.addEventListener('click', () => {
    const open = mobileMenuButton.getAttribute('aria-expanded') === 'true';
    mobileMenuButton.setAttribute('aria-expanded', String(!open));
    mobileMenu.style.maxHeight = open ? '0px' : `${mobileMenu.scrollHeight}px`;
  });
  $$('.mobile-link').forEach((link) => link.addEventListener('click', () => {
    mobileMenuButton?.setAttribute('aria-expanded', 'false');
    if (mobileMenu) mobileMenu.style.maxHeight = '0px';
  }));

  // Sticky navigation
  const header = $('#site-header');
  const nav = $('#main-nav');
  let navPlaceholderHeight = 0;
  const updateStickyNav = () => {
    const threshold = 150;
    const shouldStick = window.scrollY > threshold;
    if (shouldStick && !nav.classList.contains('is-sticky')) {
      navPlaceholderHeight = nav.offsetHeight;
      nav.classList.add('is-sticky');
      header.style.paddingBottom = `${navPlaceholderHeight}px`;
    } else if (!shouldStick && nav.classList.contains('is-sticky')) {
      nav.classList.remove('is-sticky');
      header.style.paddingBottom = '0px';
    }
  };
  window.addEventListener('scroll', updateStickyNav, { passive: true });
  updateStickyNav();

  // Hero slider
  const heroSlides = $$('.hero-slide');
  const heroDots = $$('.hero-dot');
  let heroIndex = 0;
  let heroTimer;
  function showHero(index) {
    heroIndex = (index + heroSlides.length) % heroSlides.length;
    heroSlides.forEach((slide, i) => slide.classList.toggle('is-active', i === heroIndex));
    heroDots.forEach((dot, i) => dot.classList.toggle('is-active', i === heroIndex));
  }
  function restartHero() {
    window.clearInterval(heroTimer);
    heroTimer = window.setInterval(() => showHero(heroIndex + 1), 7000);
  }
  $('#hero-prev')?.addEventListener('click', () => { showHero(heroIndex - 1); restartHero(); });
  $('#hero-next')?.addEventListener('click', () => { showHero(heroIndex + 1); restartHero(); });
  heroDots.forEach((dot) => dot.addEventListener('click', () => { showHero(Number(dot.dataset.heroDot)); restartHero(); }));
  restartHero();

  // Counters
  const counterObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const counter = entry.target;
      const target = Number(counter.dataset.target || 0);
      const start = performance.now();
      const duration = 1600;
      function animate(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        counter.textContent = Math.floor(target * eased).toLocaleString();
        if (progress < 1) requestAnimationFrame(animate);
      }
      requestAnimationFrame(animate);
      observer.unobserve(counter);
    });
  }, { threshold: 0.55 });
  $$('.counter').forEach((counter) => counterObserver.observe(counter));

  // Reveal animations
  const revealObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -45px' });
  $$('.reveal').forEach((element) => revealObserver.observe(element));

  // Project carousel
  const projectTrack = $('#projects-track');
  function scrollProjects(direction) {
    const card = $('.project-card', projectTrack);
    const distance = card ? card.getBoundingClientRect().width + 24 : 390;
    projectTrack.scrollBy({ left: distance * direction, behavior: 'smooth' });
  }
  $('#projects-prev')?.addEventListener('click', () => scrollProjects(-1));
  $('#projects-next')?.addEventListener('click', () => scrollProjects(1));

  // Testimonial slider
  const testimonialSlides = $$('.testimonial-slide');
  const testimonialDots = $$('.testimonial-dot');
  let testimonialIndex = 0;
  let testimonialTimer;
  function showTestimonial(index) {
    testimonialIndex = (index + testimonialSlides.length) % testimonialSlides.length;
    testimonialSlides.forEach((slide, i) => slide.classList.toggle('is-active', i === testimonialIndex));
    testimonialDots.forEach((dot, i) => dot.classList.toggle('is-active', i === testimonialIndex));
  }
  function restartTestimonials() {
    window.clearInterval(testimonialTimer);
    testimonialTimer = window.setInterval(() => showTestimonial(testimonialIndex + 1), 6500);
  }
  $('#testimonial-prev')?.addEventListener('click', () => { showTestimonial(testimonialIndex - 1); restartTestimonials(); });
  $('#testimonial-next')?.addEventListener('click', () => { showTestimonial(testimonialIndex + 1); restartTestimonials(); });
  testimonialDots.forEach((dot) => dot.addEventListener('click', () => { showTestimonial(Number(dot.dataset.testimonialDot)); restartTestimonials(); }));
  restartTestimonials();

  // Form helpers
  function validateForm(form) {
    let valid = true;
    $$('input, select, textarea', form).forEach((field) => {
      const invalid = field.required && !field.checkValidity();
      field.classList.toggle('is-invalid', invalid);
      if (invalid) valid = false;
    });
    return valid;
  }
  $('#estimate-form')?.addEventListener('submit', (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!validateForm(form)) {
      showToast('Please complete the required fields.');
      $('.is-invalid', form)?.focus();
      return;
    }
    form.reset();
    showToast('Thanks — your estimate request has been received.');
  });
  $$('.newsletter-form').forEach((form) => form.addEventListener('submit', (event) => {
    event.preventDefault();
    const email = $('input[type="email"]', form);
    if (!email?.checkValidity()) {
      showToast('Please enter a valid email address.');
      email?.focus();
      return;
    }
    form.reset();
    showToast('You are now subscribed.');
  }));
  $$('input, select, textarea').forEach((field) => field.addEventListener('input', () => field.classList.remove('is-invalid')));

  // Back to top
  const backToTop = $('#back-to-top');
  const updateBackToTop = () => {
    const visible = window.scrollY > 650;
    backToTop.classList.toggle('opacity-0', !visible);
    backToTop.classList.toggle('translate-y-6', !visible);
    backToTop.classList.toggle('pointer-events-none', !visible);
  };
  window.addEventListener('scroll', updateBackToTop, { passive: true });
  backToTop?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  updateBackToTop();

  // Copyright year
  $('#current-year').textContent = new Date().getFullYear();
})();
