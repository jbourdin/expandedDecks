#!/bin/sh
# Supervisord eventlistener that shuts supervisord (PID 1) down when
# worker-messenger reaches the FATAL state. Exiting PID 1 lets the
# orchestrator (Scaleway Serverless Containers, Docker --restart=always, ...)
# replace the instance with a fresh one. Without this, supervisord keeps
# serving HTTP traffic while the messenger consumer stays dead.
#
# Subscribed to PROCESS_STATE_FATAL only (see supervisord.conf), so the only
# filter needed in here is "which program just went FATAL?".
#
# Protocol: https://supervisord.org/events.html#event-listeners

set -u

while :; do
    printf "READY\n"

    # Header line: space-separated key:value pairs, one of which is len:<N>.
    if ! IFS= read -r header; then
        exit 0
    fi

    payload_length=""
    for pair in $header; do
        case "$pair" in
            len:*) payload_length="${pair#len:}"; break ;;
        esac
    done

    case "$payload_length" in
        ''|*[!0-9]*)
            printf "RESULT 4\nFAIL"
            continue
            ;;
    esac

    if [ "$payload_length" -gt 0 ]; then
        payload=$(head -c "$payload_length")
    else
        payload=""
    fi

    # Acknowledge before acting: supervisord blocks on the RESULT line, so
    # initiating shutdown without acking can wedge supervisord's own shutdown.
    printf "RESULT 2\nOK"

    case "$payload" in
        *"processname:worker-messenger"*)
            echo "worker-fatal-killer: worker-messenger entered FATAL — shutting supervisord down so the container is replaced" >&2
            supervisorctl -c /etc/supervisor/conf.d/supervisord.conf shutdown
            exit 0
            ;;
    esac
done
