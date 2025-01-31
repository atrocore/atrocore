<ul class="nav nav-pills">
    {{#each panelList}}
        <li class="bound">
            <a href="javascript:" data-action="scrollToPanel" data-name="{{name}}">{{title}}</a>
        </li>
    {{/each}}
    <div class="layout-editor-container btn-group"></div>
</ul>