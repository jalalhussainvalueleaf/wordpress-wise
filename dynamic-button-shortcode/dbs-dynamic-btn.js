document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.dbs-dynamic-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      var info = btn.getAttribute('data-info');
      if (info) {
        alert('Button clicked! Dynamic data: ' + info);
        // You can replace this with any dynamic action, e.g., fetch, modal, etc.
      }
      // Optionally prevent default if you want to handle navigation via JS
      // e.preventDefault();
    });
  });
}); 