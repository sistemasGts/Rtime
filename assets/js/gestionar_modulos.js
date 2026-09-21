/**
 * Gestionar Módulos - Lógica de permisos en cascada
 */

(function() {
    function setChildren(checkbox, checked) {
        var li = checkbox.closest('li');
        if (!li) return;
        var children = li.querySelectorAll('ul input.module-checkbox');
        children.forEach(function(child) {
            child.checked = checked;
        });
    }

    function updateAncestors(checkbox) {
        var li = checkbox.closest('li');
        if (!li) return;
        var parentLi = li.parentElement.closest('li');
        if (!parentLi) return;

        var siblingChecked = false;
        var siblings = li.parentElement.querySelectorAll('> li > label > input.module-checkbox');
        siblings.forEach(function(sibling) {
            if (sibling.checked) {
                siblingChecked = true;
            }
        });

        var parentCheckbox = parentLi.querySelector('> label > input.module-checkbox');
        if (!parentCheckbox) return;

        if (checkbox.checked) {
            parentCheckbox.checked = true;
            updateAncestors(parentCheckbox);
        } else if (!siblingChecked) {
            parentCheckbox.checked = false;
            updateAncestors(parentCheckbox);
        }
    }

    document.querySelectorAll('input.module-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            setChildren(checkbox, checkbox.checked);
            updateAncestors(checkbox);
        });
    });
})();
