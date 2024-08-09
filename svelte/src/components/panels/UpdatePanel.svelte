<script lang="ts">
    import SpinnerIcon from "../icons/loading/SpinnerIcon.svelte";
    import {afterUpdate} from "svelte";

    export let applicationName: string = 'AtroCore';
    export let logoPath: URL | null = null;
    export let logFilePath: URL;
    export let restoreLink: URL | null = null;

    let showLogs: boolean = false;
    let updateStarted: boolean = false;
    let seconds: number = 0;
    let autoScroll: boolean = true;
    let fullLogs: string = '';
    let lastLine: string;
    let logsContainer: Element;

    $: if (fullLogs) {
        const lines = fullLogs.trim().split('\n');
        lastLine = formatLogLine(lines.pop());
    } else if (!updateStarted) {
        lastLine = 'Starting...';
    } else {
        lastLine = '';
    }

    afterUpdate(() => {
        if (logsContainer && autoScroll) {
            logsContainer.scrollTo(0, logsContainer.scrollHeight)
        }
    });

    function showLogsButtonHandler() {
        showLogs = !showLogs;
        if (!showLogs) {
            autoScroll = true;
        }
    }

    function logsScrollHandler(e) {
        const target: Element = e.currentTarget;
        autoScroll = target.scrollHeight - target.scrollTop === target.clientHeight;
    }

    function formatLogLine(line: string): string {
        return line
            .replace(/^\d{2,4}.\d{2}.\d{2,4} \d{2}:\d{2}:\d{2}/g, '')
            .replace(/^[\-\|\s]+/, '');
    }

    setInterval(async () => {
        try {
            const response = await fetch(logFilePath, {cache: "no-store"});
            seconds += 1;

            if (response.ok) {
                fullLogs = (await response.text()).trim();

                if (fullLogs.search("composer") >= 0) {
                    updateStarted = true;
                }

                if (seconds > 65 && !updateStarted) {
                    fullLogs = 'Something wrong. Please, reboot the server.';
                }
            } else {
                if (updateStarted) {
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else if (seconds > 65) {
                    fullLogs = 'Something wrong. Please, reboot the server.';
                }
            }
        } catch (error) {
            console.error('Error: ', error);
        }
    }, 1000);
</script>

<div id="login" class="panel panel-default panel-updating">
    <div class="panel-heading">
        <div class="logo-container">
            {#if logoPath}
                <img src={logoPath} class="logo" alt={applicationName}>
            {/if}
        </div>
    </div>
    <div class="panel-body">
        <div class="form-group"><h3>System is updating...</h3>
            <section class="progress-section">
                <SpinnerIcon size="40" thickness="5"/>
                <h5>{lastLine}</h5>
            </section>
            <section class="logs-section">
                <div class="buttons">
                    <button class="btn btn-default" on:click={showLogsButtonHandler}><i
                            class="fa fa-list"></i>{showLogs ? 'Hide' : 'Show'} logs
                    </button>
                    {#if restoreLink}
                        <a href={restoreLink} target="_blank" class="btn btn-default" role="button"
                           style="float: right;"><i
                                class="fa fa-history"></i>Restore the system</a>
                    {/if}
                </div>
                {#if showLogs}
                    <hr>
                    <pre class="logs-container" bind:this={logsContainer} on:scroll={logsScrollHandler}>{fullLogs}</pre>
                {/if}
            </section>
        </div>
    </div>
</div>

<style>
    .progress-section {
        margin: 4em 0 3em;
        text-align: center;
    }

    h5 {
        font-weight: 400;
    }

    .btn > i {
        margin-right: .5em;
    }

    .logs-container {
        max-height: 300px;
        overflow: auto;
    }
</style>