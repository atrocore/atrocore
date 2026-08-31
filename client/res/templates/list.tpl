<div class="content-wrapper">
    <div class="list-page-body">
        <main>
            <div class="page-header-container">
                <div class="page-header">{{{header}}}</div>
                <div class="list-buttons-container"></div>
            </div>
            <div {{#if isTreeAllowed}}id="tree-list-table" {{/if}}class="list-container">{{{list}}}</div>
        </main>
        <div class="list-pagination-container"></div>
    </div>
</div>