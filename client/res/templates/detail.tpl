<div class="content-wrapper">
    {{#if treeAllowed}}
        <aside class="tree-panel-anchor"></aside>
    {{/if}}
    <main>
        <div class="header page-header">{{{header}}}</div>
        <div class="record">{{{record}}}</div>
        <div class="bottom">{{{bottom}}}</div>
    </main>
    {{#if sideAllowed}}
        <aside class="side">{{{side}}}</aside>
    {{/if}}
</div>