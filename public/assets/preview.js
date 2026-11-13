(function () {
    var top = document.getElementById('top_text');
    var bottom = document.getElementById('bottom_text');
    var previewTop = document.getElementById('preview-top');
    var previewBottom = document.getElementById('preview-bottom');
    var previewImg = document.getElementById('preview-img');
    var template = document.getElementById('template');

    function syncText() {
        previewTop.textContent = top.value;
        previewBottom.textContent = bottom.value;
    }
    top.addEventListener('input', syncText);
    bottom.addEventListener('input', syncText);

    if (template) {
        template.addEventListener('change', function () {
            var opt = template.options[template.selectedIndex];
            previewImg.src = opt.getAttribute('data-src');
        });
    }

    // Show only the inputs for the chosen image source.
    var radios = document.querySelectorAll('input[name="source"]');
    function syncSource() {
        radios.forEach(function (r) {
            var panel = document.getElementById('source-' + r.value);
            if (panel) { panel.hidden = !r.checked; }
        });
    }
    radios.forEach(function (r) { r.addEventListener('change', syncSource); });
    syncSource();
})();