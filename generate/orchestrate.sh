#!/bin/bash
# Born2beRoot — main orchestrator (live TUI dashboard)
# Called by: make all
set -e

VM_NAME="${1:-debian}"
MAKE_CMD="${2:-make}"
LOG_DIR=$(mktemp -d)

# ── Colours ──────────────────────────────────────────────────────────────────
RST='\033[0m';  BLD='\033[1m';  DIM='\033[2m'
GRN='\033[32m'; YLW='\033[33m'; RED='\033[31m'
BLU='\033[34m'; CYN='\033[36m'; WHT='\033[97m'
HIDE_CUR='\033[?25l'; SHOW_CUR='\033[?25h'
CLR='\033[2K'

# Early trap (before functions are defined)
trap 'printf "${SHOW_CUR}"; rm -rf "$LOG_DIR"' EXIT INT TERM

# ── Box drawing (single-line, rounded corners) ───────────────────────────────
W=60  # inner visible width between │ chars

top()  { printf "  ${CYN}╭"; printf '─%.0s' $(seq 1 $W); printf "╮${RST}\n"; }
mid()  { printf "  ${CYN}├"; printf '─%.0s' $(seq 1 $W); printf "┤${RST}\n"; }
bot()  { printf "  ${CYN}╰"; printf '─%.0s' $(seq 1 $W); printf "╯${RST}\n"; }
blank(){ printf "  ${CYN}│${RST}%${W}s${CYN}│${RST}\n" ""; }

# Print a row: content is padded to exactly W visible chars
row() {
    local content="$1"
    # Strip ANSI to measure visible length
    local stripped
    stripped=$(printf '%b' "$content" | sed 's/\x1b\[[0-9;]*m//g')
    local vlen
    vlen=$(printf '%s' "$stripped" | wc -m)
    local pad=$((W - vlen))
    [ "$pad" -lt 0 ] && pad=0
    printf "  ${CYN}│${RST}"
    printf '%b' "$content"
    printf '%*s' "$pad" ""
    printf "${CYN}│${RST}\n"
}

# Centered row
crow() {
    local content="$1"
    local stripped
    stripped=$(printf '%b' "$content" | sed 's/\x1b\[[0-9;]*m//g')
    local vlen
    vlen=$(printf '%s' "$stripped" | wc -m)
    local total_pad=$((W - vlen))
    local lpad=$((total_pad / 2))
    local rpad=$((total_pad - lpad))
    [ "$lpad" -lt 0 ] && lpad=0
    [ "$rpad" -lt 0 ] && rpad=0
    printf "  ${CYN}│${RST}"
    printf '%*s' "$lpad" ""
    printf '%b' "$content"
    printf '%*s' "$rpad" ""
    printf "${CYN}│${RST}\n"
}

# ── Step tracking ────────────────────────────────────────────────────────────
STEPS=("VirtualBox" "Preseeded ISO" "VM Setup" "VM Start")
STEP_STATUS=("pending" "pending" "pending" "pending")
STEP_DETAIL=("" "" "" "")
DASHBOARD_LINES=0

# Braille spinner (static frame per step — no background process)
SPIN_FRAMES=('⠋' '⠙' '⠹' '⠸' '⠼' '⠴' '⠦' '⠧' '⠇' '⠏')
SPIN_IDX=0
SPIN_LEN=${#SPIN_FRAMES[@]}

draw_dashboard() {
    local first_draw="${1:-false}"
    if [ "$first_draw" != "true" ] && [ "$DASHBOARD_LINES" -gt 0 ]; then
        printf "\033[${DASHBOARD_LINES}A"
    fi
    local lines=0

    printf "${CLR}"; top;  lines=$((lines+1))
    printf "${CLR}"; crow "${BLD}${WHT}Born2beRoot  ─  VM Provisioner${RST}"; lines=$((lines+1))
    printf "${CLR}"; mid;  lines=$((lines+1))

    for i in "${!STEPS[@]}"; do
        local name="${STEPS[$i]}"
        local st="${STEP_STATUS[$i]}"
        local det="${STEP_DETAIL[$i]}"
        local icon color label

        # Advance spinner index so each redraw shows a new frame
        SPIN_IDX=$(( (SPIN_IDX + 1) % SPIN_LEN ))

        case "$st" in
            pending) icon="·"; color="${DIM}";  label="waiting"    ;;
            working) icon="${SPIN_FRAMES[$SPIN_IDX]}"; color="${BLU}";  label="working..." ;;
            done)    icon="✓"; color="${GRN}";  label="done"       ;;
            skip)    icon="✓"; color="${GRN}";  label="ready"      ;;
            fail)    icon="✗"; color="${RED}";  label="FAILED"     ;;
        esac

        local det_str=""
        [ -n "$det" ] && det_str=" ${DIM}${det}${RST}"

        local padded_name
        padded_name=$(printf "%-16s" "$name")

        printf "${CLR}"
        row "  ${color}${BLD}${icon}${RST}  ${color}${padded_name}${RST} ${color}${label}${RST}${det_str}"
        lines=$((lines+1))
    done

    printf "${CLR}"; bot; lines=$((lines+1))
    DASHBOARD_LINES=$lines
}

# ── Spinner: a tiny background subshell that only redraws 1 character ────────
# It writes the braille spinner char at a fixed (row, col) on the terminal.
# The actual command runs in the foreground — this is display-only.
SPINNER_PID=""

start_spinner() {
    local lines_up="$1"
    (
        trap 'exit 0' TERM INT
        local f=('⠋' '⠙' '⠹' '⠸' '⠼' '⠴' '⠦' '⠧' '⠇' '⠏')
        local i=0
        while true; do
            # save cursor → move up → go to col 6 → print spinner → restore cursor
            printf "\0337\033[%dA\r\033[5C\033[1;34m%s\033[0m\0338" \
                "$lines_up" "${f[$i]}"
            i=$(( (i + 1) % 10 ))
            sleep 0.1
        done
    ) &
    SPINNER_PID=$!
}

stop_spinner() {
    if [ -n "$SPINNER_PID" ]; then
        kill "$SPINNER_PID" 2>/dev/null
        wait "$SPINNER_PID" 2>/dev/null
        SPINNER_PID=""
    fi
}

# Override trap now that stop_spinner is defined
trap 'stop_spinner; printf "${SHOW_CUR}"; rm -rf "$LOG_DIR"' EXIT INT TERM

# ── Run step in FOREGROUND with animated spinner ─────────────────────────────
run_step() {
    local idx="$1"; shift
    local log="${LOG_DIR}/step_${idx}.log"
    STEP_STATUS[$idx]="working"
    draw_dashboard

    # Spinner targets the row of step $idx
    # After draw_dashboard cursor is below bot border:
    #   1 up = bot, 2 up = last step, ... so step $idx = (num_steps - idx + 1) up
    local lines_up=$(( ${#STEPS[@]} - idx + 1 ))
    start_spinner "$lines_up"

    # Run the actual command in FOREGROUND (blocks until done)
    local rc=0
    "$@" > "$log" 2>&1 || rc=$?

    # Kill spinner, update state, redraw
    stop_spinner

    if [ "$rc" -eq 0 ]; then
        STEP_STATUS[$idx]="done"
        draw_dashboard
    else
        STEP_STATUS[$idx]="fail"
        draw_dashboard
        printf "\n${RED}${BLD}  ── Error log: ${STEPS[$idx]} ──${RST}\n${DIM}"
        tail -30 "$log" | sed 's/^/    /'
        printf "${RST}\n"; exit 1
    fi
}

# ── Detect host IP (cross-platform) ─────────────────────────────────────────
get_host_ip() {
    if command -v ip >/dev/null 2>&1; then
        ip route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -1
    elif command -v hostname >/dev/null 2>&1; then
        hostname -I 2>/dev/null | awk '{print $1}'
    else
        echo "127.0.0.1"
    fi
}

# ═════════════════════════════════════════════════════════════════════════════
#  MAIN
# ═════════════════════════════════════════════════════════════════════════════
printf "${HIDE_CUR}\n"
draw_dashboard true

# Step 1 — VirtualBox
if command -v VBoxManage >/dev/null 2>&1; then
    STEP_STATUS[0]="skip"; STEP_DETAIL[0]="v$(VBoxManage --version 2>/dev/null)"
    draw_dashboard
else
    run_step 0 ${MAKE_CMD} --no-print-directory deps
    STEP_DETAIL[0]="v$(VBoxManage --version 2>/dev/null)"; draw_dashboard
fi

# Step 2 — Preseeded ISO
PRESEED_ISO=$(ls -1 debian-*-amd64-*preseed.iso 2>/dev/null | head -n1)
if [ -n "$PRESEED_ISO" ]; then
    STEP_STATUS[1]="skip"; STEP_DETAIL[1]="$PRESEED_ISO"; draw_dashboard
else
    run_step 1 ${MAKE_CMD} --no-print-directory gen_iso
    PRESEED_ISO=$(ls -1 debian-*-amd64-*preseed.iso 2>/dev/null | head -n1)
    STEP_DETAIL[1]="$PRESEED_ISO"; draw_dashboard
fi

# Step 3 — VM creation
# Check VM exists AND its disk is intact (not just registered)
VM_OK=false
if VBoxManage showvminfo "${VM_NAME}" >/dev/null 2>&1; then
    VM_VDI=$(VBoxManage showvminfo "${VM_NAME}" --machinereadable 2>/dev/null \
        | grep '"SATA Controller-0-0"' | cut -d'"' -f4)
    if [ -n "$VM_VDI" ] && [ -f "$VM_VDI" ]; then
        VM_OK=true
    else
        # Stale VM registration — disk is missing, clean it up
        VBoxManage unregistervm "${VM_NAME}" --delete 2>/dev/null || true
    fi
fi

if [ "$VM_OK" = true ]; then
    STEP_STATUS[2]="skip"; STEP_DETAIL[2]="${VM_NAME}"; draw_dashboard
else
    run_step 2 ${MAKE_CMD} --no-print-directory setup_vm
    STEP_DETAIL[2]="${VM_NAME}"; draw_dashboard
fi

# Step 4 — Start VM
VM_STATE=$(VBoxManage showvminfo "${VM_NAME}" --machinereadable 2>/dev/null \
    | grep "^VMState=" | cut -d'"' -f2)
if [ "$VM_STATE" = "running" ]; then
    STEP_STATUS[3]="skip"; STEP_DETAIL[3]="already running"; draw_dashboard
else
    run_step 3 VBoxManage startvm "${VM_NAME}" --type gui
    STEP_DETAIL[3]="launched"; draw_dashboard
fi

# ── Read actual ports from VM config (no hardcoding) ─────────────────────────
get_vm_port() {
    # Extract host port from a NAT forwarding rule
    # Rule format: "name,tcp,,HOSTPORT,,GUESTPORT"
    local name="$1"
    local line
    line=$(VBoxManage showvminfo "${VM_NAME}" --machinereadable 2>/dev/null \
        | grep "^Forwarding" | grep "\"${name}")
    # If searching for "http", exclude "https" matches
    if [ "$name" = "http" ]; then
        line=$(echo "$line" | grep -v "\"https")
    fi
    echo "$line" | head -1 | cut -d',' -f4
}

# Find a free port for the preseed HTTP server (not used by VM or system)
find_free_port() {
    local port="$1"
    local max=100 i=0
    while [ "$i" -lt "$max" ]; do
        if ! (ss -tln 2>/dev/null || netstat -tln 2>/dev/null) | grep -qE "(0\.0\.0\.0|\*|\[::\]):${port}\b"; then
            echo "$port"; return 0
        fi
        port=$((port + 1)); i=$((i + 1))
    done
    echo "$1"  # fallback
}

P_SSH=$(get_vm_port ssh)
P_HTTP=$(get_vm_port http)
P_HTTPS=$(get_vm_port https)
P_DOCKER=$(get_vm_port docker)
P_MARIADB=$(get_vm_port mariadb)
P_REDIS=$(get_vm_port redis)
P_PRESEED=$(find_free_port 8080)

# ── Summary ──────────────────────────────────────────────────────────────────
HOST_IP=$(get_host_ip)
printf "${SHOW_CUR}\n"

top
crow "${GRN}${BLD}✓  All Steps Completed${RST}"
mid
blank
row "  ${BLD}${WHT}▸ What happens now${RST}"
row "    The VM boots the preseeded ISO and installs Debian"
row "    automatically (partitioning, SSH, WordPress, etc)."
blank
mid
row "  ${BLD}${WHT}▸ Credentials${RST}"
row "    ${DIM}root password${RST}      ${GRN}temproot123${RST}"
row "    ${DIM}user (dlesieur)${RST}    ${GRN}tempuser123${RST}"
row "    ${DIM}disk encryption${RST}    ${GRN}tempencrypt123${RST}"
blank
mid
row "  ${BLD}${WHT}▸ After Reboot${RST}"
row "    ${YLW}1.${RST} Disk passphrase:  ${GRN}tempencrypt123${RST}"
row "    ${YLW}2.${RST} Log in:  ${GRN}dlesieur${RST} / ${GRN}tempuser123${RST}"
blank
mid
row "  ${BLD}${WHT}▸ Connect from Host${RST}"
row "    ${DIM}SSH${RST}        ${BLD}ssh -p ${P_SSH} dlesieur@127.0.0.1${RST}"
row "    ${DIM}WordPress${RST}  ${BLD}http://127.0.0.1:${P_HTTP}/wordpress${RST}"
blank
mid
row "  ${BLD}${WHT}▸ Preseed via HTTP (alternative)${RST}"
row "    ${DIM}Host LAN IP:${RST}   ${GRN}${HOST_IP}${RST}"
row "    ${DIM}NAT gateway:${RST}   ${GRN}10.0.2.2${RST}  ${DIM}(host seen from VM)${RST}"
blank
row "    ${DIM}Serve preseed on your host:${RST}"
row "      ${BLD}cd preseeds && python3 -m http.server ${P_PRESEED}${RST}"
blank
row "    ${DIM}Use this URL in the Debian installer:${RST}"
row "      ${BLD}http://10.0.2.2:${P_PRESEED}/preseed.cfg${RST}"
blank
mid
row "  ${BLD}${WHT}▸ Port Forwarding (VM NAT)${RST}"
row "    ${DIM}SSH${RST}      ${WHT}:${P_SSH}${RST}    ${DIM}HTTP${RST}   ${WHT}:${P_HTTP}${RST}    ${DIM}HTTPS${RST}  ${WHT}:${P_HTTPS}${RST}"
row "    ${DIM}Docker${RST}   ${WHT}:${P_DOCKER}${RST}    ${DIM}MariaDB${RST} ${WHT}:${P_MARIADB}${RST}   ${DIM}Redis${RST}  ${WHT}:${P_REDIS}${RST}"
blank
mid
row "  ${BLD}${WHT}▸ Useful Commands${RST}"
row "    ${BLU}make status${RST}      check current state"
row "    ${BLU}make poweroff${RST}    shut down the VM"
row "    ${BLU}make re${RST}          destroy and rebuild"
blank
bot
printf "\n"
