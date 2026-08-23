<div class="content-wrapper">
    <main>
        <div class="page-header-container">
            <div class="page-header">{{{header}}}</div>
            <div class="list-buttons-container"></div>
        </div>
        <div {{#if isTreeAllowed}}id="tree-list-table" {{/if}}class="list-container">{{{list}}}</div>
        <div class="list-pagination-container"></div>
    </main>
</div>