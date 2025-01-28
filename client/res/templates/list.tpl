{{#if treePanel}}
    <aside class="catalog-tree-panel">{{{treePanel}}}</aside>
{{/if}}
<main>
    <div class="page-header">{{{header}}}</div>
    <div {{#if treePanel}}id="tree-list-table" {{/if}}class="list-container">{{{list}}}</div>
</main>