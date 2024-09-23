<script lang="ts">
    import {Language} from "../../../utils/Language";
    import {onMount} from "svelte";
    import {ModelFactory} from "../../../utils/ModelFactory";
    export let  afterOnMount = () => null;
    export let  sendRequest = () => null;

    let content =  null;

    let response = null;
    let model = null;


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
                <a href="#Admin">{ Language.translate('Administration')}</a>{Language.translate('apiRequest', 'labels', 'Admin')}
            </div>
        </div>
        <div class="header-title">{Language.translate('apiRequest', 'labels', 'Admin')}</div>
    </h3>

    <button on:click={sendRequest(model)} style="margin: 10px 7px 10px 5px" class="btn btn-primary action" data-action="execute"
            type="button">{Language.translate('execute', 'labels', 'Admin')}</button>
</div>

<div class="row">
    <div class="col-sm-3">
        <div class="row">
            <div class="col-xs-12 cell form-group">
                <label class="control-label"
                       data-name="type">{Language.translate('Type', 'fields','Admin')}</label>
                <div class="field" data-name="type"></div>

            </div>

        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-12 col-md-6 cell form-group">
        <label class="control-label" data-name="request">{Language.translate('request', 'fields', 'Admin')}</label>
        <div class="field" data-name="request"></div>
    </div>
    <div class="col-sm-12 col-md-6 cell form-group">
        <label style="width:100%" class="control-label" data-name="response">{Language.translate('response', 'fields', 'Admin')}<span class="pull-right status hidden">

                    {Language.translate('status','labels', 'Admin')}: <span></span>
                </span></label>
        <div class="field" data-name="response"></div>
    </div>
</div>




