
<ul class="pagination pagination-sm">
    <li {{#unless previous}}class="disabled"{{/unless}}>
        <a href="javascript:" data-page="first"> <i class="ph ph-skip-back"></i> </a>
    </li>
    <li {{#unless previous}}class="disabled"{{/unless}}>
        <a href="javascript:" data-page="previous"> <i class="ph ph-rewind"></i> </a>
    </li>
    <li>
        <a href="javascript:" data-page="current"> {{from}} - {{to}} {{translate 'of'}} {{total}} </a>
    </li>
    <li {{#unless next}}class="disabled"{{/unless}}>
        <a href="javascript:" data-page="next"> <i class="ph ph-fast-forward"></i> </a>
    </li>
    <li {{#unless next}}class="disabled"{{/unless}}>
        <a href="javascript:" data-page="last"> <i class="ph ph-skip-forward"></i> </a>
    </li>
</ul>


