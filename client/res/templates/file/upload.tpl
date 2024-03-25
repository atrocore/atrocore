<div class="attachment-upload">
    <div class="clearfix attachment-control">
        <div class="center-block drag-and-drop-files">
            <label class="attach-file-label" title="{{translate 'attachFile'}}">
                <span id="upload-area" class="btn btn-default">
                    <span class="btn-text">{{translate 'dragAndDropBtnText'}}</span>
                    <span class="btn-text btn-text-small">{{translate 'dragAndDropBtnTextSmall'}}</span>
                    <span class="btn-upload">
                        <span id="upload-btn" class="btn btn-primary">{{translate 'upload'}}</span>
                    </span>
                </span>
                <input id="upload-input" type="file" class="file pull-right" {{#if multiUpload}}multiple{{/if}}>
            </label>
        </div>

        {{#if sourceList.length}}
        <div class="pull-left dropdown">
            <button class="btn btn-default btn-icon dropdown-toggle" type="button" data-toggle="dropdown">
                <span class="fas fa-file fa-sm"></span>
            </button>
            <ul class="dropdown-menu" role="menu">
            {{#each sourceList}}
                <li><a href="javascript:" class="action" data-action="insertFromSource" data-name="{{./this}}">{{translate this category='insertFromSourceLabels' scope='Attachment'}}</a></li>
            {{/each}}
            </ul>
        </div>
        {{/if}}
    </div>
    <div class="progress" style="display:none">
        <div class="progress-bar progress-bar-info" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
    <div class="attachments"></div>
</div>

<style type="text/css">
    .attachment-upload .progress {
        white-space: nowrap;
        width: 100%;
    }
    .attachment-upload .progress .progress-bar {
        color: black;
    }
    .file-uploading-success{
        background-color: #d2f7d0 !important;
    }
    .file-uploading-failed{
        background-color: #f2dede !important;
    }
</style>
