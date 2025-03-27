<ul class="nav nav-pills">
    {{#each panelList}}
        <li class="bound">
            <a href="javascript:" data-action="scrollToPanel" data-name="{{name}}">{{title}}</a>
        </li>
    {{/each}}
    <li>
        <span class="layout-editor-container btn-group"></span>
    </li>
</ul>