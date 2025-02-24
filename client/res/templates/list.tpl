<div class="content-wrapper">
    {{#if isTreeAllowed}}
        <aside class="tree-panel-anchor"></aside>
    {{/if}}
    <main>
        <div class="page-header">{{{header}}}</div>
        <div {{#if isTreeAllowed}}id="tree-list-table" {{/if}}class="list-container">{{{list}}}</div>
    </main>
</div>