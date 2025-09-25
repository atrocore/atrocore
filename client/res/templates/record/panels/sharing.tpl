<div class="list-container sharing-panel">
    {{{list}}}
</div>

{{#if canShare}}
    <button data-action="createSharing" class="action share-button"><i class="ph ph-share-network"></i><span>{{ translate 'Share' category='labels' scope='File' }}</span></button>
{{/if}}

<style>
    .sharing-panel {
        padding: 10px 0;
    }

    .sharing-panel > .list {
        margin-top: 0 !important;
        margin-bottom: -5px !important;
    }

    .sharing-panel > .list > table thead {
        display: none;
    }

    .sharing-panel > .list > table {
        position: relative;
        top: -6px;
    }

    .sharing-panel > .list > table .cell[data-name=link],
    .sharing-panel > .list > table .cell[data-name=available] {
        padding-right: 0;
    }

    .sharing-panel > .list > table .cell[data-name=buttons] {
        padding-left: 0;
        width: 30px;
    }

    .modal-body .sharing-panel > .list > table .cell {
        padding: 8px;
    }

    .panel-body[data-name="sharing"] {
        padding: 0 14px;
    }

    .modal-body .panel-body[data-name="sharing"] {
        padding: 0 0 0 14px;
    }

    .sharing-panel .cell[data-name="name"] {
        max-width: 95px;
    }

    .sharing-panel.no-data + .share-button {
        margin-top: 15px;
    }

    .share-button {
        width: 100%;
    }

    .sharing-panel .copy-link {
        border: 0;
        padding: 0;
        background-color: transparent;
        color: #000;
    }
</style>