<script lang="ts">
    export let showModal = false;
</script>

{#if showModal}
    <modal-window scene="overlayed" position="t-right" modal-mobile-center modal="manager">
        <header background="primary" padding="small">
            title
            <button icon="close" icon-size="20" type="link" size="small" close-modal-window></button>
        </header>
        <table-container>
            <table no-vborders padding striped>
                <tbody>
                <tr>
                    <td padding>This is some text</td>
                    <td padding><status background="warning" padding="small" size="small">In Progress</status></td>
                    <td padding>This is some text</td>
                    <td padding><button label="Cancel" type="button" size="small" style-color="danger"></button></td>
                </tr>
                <tr>
                    <td padding>This is some text</td>
                    <td padding><status background="warning" padding="small" size="small">In Progress</status></td>
                    <td padding>This is some text</td>
                    <td padding><button label="Cancel" type="button" size="small" style-color="danger"></button></td>
                </tr>
                <tr>
                    <td padding>This is some text</td>
                    <td padding><status background="warning" padding="small" size="small">In Progress</status></td>
                    <td padding>This is some text</td>
                    <td padding><button label="Cancel" type="button" size="small" style-color="danger"></button></td>
                </tr>
                <tr>
                    <td padding>This is some text</td>
                    <td padding><status background="warning" padding="small" size="small">In Progress</status></td>
                    <td padding>This is some text</td>
                    <td padding><button label="Cancel" type="button" size="small" style-color="danger"></button></td>
                </tr>
                <tr>
                    <td padding>This is some text</td>
                    <td padding><status background="warning" padding="small" size="small">In Progress</status></td>
                    <td padding>This is some text</td>
                    <td padding><button label="Cancel" type="button" size="small" style-color="danger"></button></td>
                </tr>
                </tbody>
            </table>
        </table-container>
        <footer padding="small">
            <button label="Load more" size="small" type="button" style-color="warning"></button>
            <button label="Show all" size="small" type="button" style-color="secondary"></button>
        </footer>
    </modal-window>
{/if}
