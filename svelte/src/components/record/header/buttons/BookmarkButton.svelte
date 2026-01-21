<script lang="ts">
    import ActionParams from "../interfaces/ActionParams";
    import ActionButton from "$lib/components/buttons/ActionButton/ActionButton.svelte";
    import {Notifier} from "../../../../utils/Notifier";
    import {Language} from "../../../../utils/Language";
    import {UserData} from "../../../../utils/UserData";

    export let entity: string;
    export let id: string;
    export let bookmarkId: string | null = null;
    export let loading: boolean = false;

    let style: string;
    let iconStyle: string;
    let params: ActionParams;

    $: {
        style = bookmarkId ? 'primary outline' : '';
        iconStyle = bookmarkId ? 'ph-fill' : 'ph';
        params = {
            name: 'bookmarking',
            action: bookmarkId ? 'unbookmark' : 'bookmark',
            html: `<i class="${iconStyle} ph-bookmark-simple"></i>`,
            style: style,
            disabled: loading,
            tooltip: Language.translate(bookmarkId ? 'actionUnbookmark' : 'actionBookmark'),
        } as ActionParams;
    }

    async function addBookmark(): Promise<void> {
        const userData = UserData.get();
        if (!userData) {
            return;
        }

        Notifier.notify(Language.translate('Bookmarking') + '...');
        loading = true;

        try {
            const response = await fetch('/api/v1/Bookmark', {
                'method': 'POST',
                'headers': {
                    'Content-Type': 'application/json',
                    'Authorization-Token': btoa(userData.user.userName + ':' + userData.token)
                },
                'body': JSON.stringify({
                    'entityType': entity,
                    'entityId': id,
                }),
            })

            if (response.ok) {
                const data = await response.json();
                bookmarkId = data.id;
                Notifier.notify(Language.translate('Done'), 'success');
            }
        } catch (e) {
            console.error('Error on adding bookmark', e);
        } finally {
            loading = false;
        }
    }

    async function removeBookmark(): Promise<void> {
        const userData = UserData.get();
        if (!userData || !bookmarkId) {
            return;
        }

        Notifier.notify(Language.translate('Unbookmarking') + '...');
        loading = true;

        try {
            const response = await fetch('/api/v1/Bookmark/' + bookmarkId, {
                'method': 'DELETE',
                'headers': {
                    'Authorization-Token': btoa(userData.user.userName + ':' + userData.token),
                    'permanently': 'true',
                },
            });

            if (response.ok) {
                bookmarkId = null;
                Notifier.notify(Language.translate('Done'), 'success');
            }
        } catch (e) {
            console.error('Error on removing bookmark', e);
        } finally {
            loading = false;
        }
    }

    function execute(e: CustomEvent): void {
        if (params.action === 'unbookmark') {
            removeBookmark();
        } else {
            addBookmark();
        }
    }
</script>

<ActionButton {params} on:execute={execute}/>