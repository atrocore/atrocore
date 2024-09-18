<script lang="ts">
    import {Language} from "../../../utils/Language";
    import {onMount} from "svelte";
    import {ModelFactory} from "../../../utils/ModelFactory";
    import { JSONEditor } from 'svelte-jsoneditor'
    export let  afterOnMount = () => null;

    let content =  null;

    let response = null;
    let model = null;

    function onPaste(e){
        console.log('passste', e)
        content = JSON.stringify(JSON.parse(content, undefined, 4))
    }

    onMount(() => {
        ModelFactory.create('', function (m) {
           model = m;
            afterOnMount(model);
        })
    })

</script>

<div class="page-header">
    <h3>
        <div class="header-breadcrumbs fixed-header-breadcrumbs">
            <div class="breadcrumbs-wrapper">
                <a href="#Admin">{ Language.translate('Administration')}</a>{Language.translate('Api Request', 'Admin')}
            </div>
        </div>
        <div class="header-title">{Language.translate('Api Request', 'Admin')}</div>
    </h3>

    <button style="margin: 10px 7px 10px 5px" class="btn btn-primary action" data-action="runApi"
            type="button">{Language.translate('runApiRequest')}</button>
</div>

<div class="row">
    <div class="col-sm-3">
        <div class="row">
            <div class="col-xs-12 cell form-group">
                <label class="control-label"
                       data-name="type">{Language.translate('Type')}</label>
                <div class="field" data-name="type"></div>
            </div>

        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-12 col-md-6 cell form-group">
        <label class="control-label" data-name="request">{Language.translate('Request')}</label>
        <div class="field" data-name="request"></div>
    </div>
    <div class="col-sm-12 col-md-6 cell form-group">
        <label class="control-label" data-name="response">{Language.translate('Response')}</label>
        <div class="field" data-name="response"></div>
    </div>
</div>




