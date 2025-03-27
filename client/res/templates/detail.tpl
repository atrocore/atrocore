
<div class="content-wrapper">
    {{#if treeAllowed}}
        <aside class="tree-panel-anchor"></aside>
    {{/if}}
    <main>
        <div class="header page-header">{{{header}}}</div>
        <div class="record">{{{record}}}</div>
        <div class="bottom">{{{bottom}}}</div>
    </main>

</div>
<style>
    /*#main:has(.detail) {*/
    /*    display: flex;*/
    /*}*/

    /*body > .content:has(.detail) {*/
    /*    overflow-y: hidden;*/
    /*}*/

    /*#main > main:has(.detail) {*/
    /*    overflow-y: auto;*/
    /*    width: 100%;*/
    /*}*/
</style>