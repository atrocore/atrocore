<div class="content-wrapper">
    {{#if isTreeAllowed}}
        <aside class="tree-panel-anchor"></aside>
    {{/if}}
    <main>
        <div class="page-header-container">
            <div class="page-header">{{{header}}}</div>
        </div>
        <div class="list-buttons-container"></div>
        <div {{#if isTreeAllowed}}id="tree-list-table" {{/if}}class="list-container">{{{list}}}</div>
    </main>
</div>