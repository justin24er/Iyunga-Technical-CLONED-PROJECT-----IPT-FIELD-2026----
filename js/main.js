const navLinks = document.querySelectorAll('.site-nav a');

navLinks.forEach(link => {
  if (link.href === window.location.href) {
    link.classList.add('active');
  }
});

console.log('IYUNGA Secondary School website loaded.');
