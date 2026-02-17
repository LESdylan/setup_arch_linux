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

# Step 4 — Start VM (install from ISO)
VM_STATE=$(VBoxManage showvminfo "${VM_NAME}" --machinereadable 2>/dev/null \
    | grep "^VMState=" | cut -d'"' -f2)
if [ "$VM_STATE" = "running" ]; then
    STEP_STATUS[3]="skip"; STEP_DETAIL[3]="already running"; draw_dashboard
else
    run_step 3 VBoxManage startvm "${VM_NAME}" --type gui
    STEP_DETAIL[3]="installing..."; draw_dashboard
fi

# ── Wait for VM to fully unlock after poweroff ──────────────────────────────
# VBoxManage controlvm poweroff returns immediately but the session lock
# takes several seconds to release. modifyvm will FAIL if we don't wait.
wait_for_vm_unlock() {
    local max_wait=30
    local i=0
    while [ "$i" -lt "$max_wait" ]; do
        local st
        st=$(VBoxManage showvminfo "${VM_NAME}" --machinereadable 2>/dev/null \
            | grep "^VMState=" | cut -d'"' -f2)
        if [ "$st" = "poweroff" ] || [ "$st" = "aborted" ] || [ "$st" = "saved" ]; then
            # Try a harmless modifyvm to see if the lock is actually released
            if VBoxManage modifyvm "${VM_NAME}" --description "b2b" 2>/dev/null; then
                return 0
            fi
        fi
        sleep 1
        i=$((i + 1))
    done
    return 1  # still locked after 30s
}

# ── Switch boot order from DVD to disk (with retries) ───────────────────────
switch_boot_to_disk() {
    local max_retries=5
    local attempt=0
    while [ "$attempt" -lt "$max_retries" ]; do
        if VBoxManage modifyvm "${VM_NAME}" --boot1 disk --boot2 dvd --boot3 none --boot4 none 2>/dev/null; then
            VBoxManage storageattach "${VM_NAME}" --storagectl "IDE Controller" \
                --port 0 --device 0 --medium emptydrive 2>/dev/null || true
            return 0
        fi
        sleep 3
        attempt=$((attempt + 1))
    done
    # Last-resort: the lock may be truly stuck — kill any leftover VBox processes
    # for this VM and try one more time
    VBoxManage controlvm "${VM_NAME}" poweroff 2>/dev/null || true
    sleep 5
    VBoxManage modifyvm "${VM_NAME}" --boot1 disk --boot2 dvd --boot3 none --boot4 none 2>/dev/null || true
    VBoxManage storageattach "${VM_NAME}" --storagectl "IDE Controller" \
        --port 0 --device 0 --medium emptydrive 2>/dev/null || true
}

# ── Wait for install to finish (VM will power off) then boot from disk ───
# The preseed sets exit/poweroff=true so the VM shuts down after install.
# We wait for that, then switch boot order from DVD→disk to disk→DVD,
# detach the ISO, and start the VM to boot from the installed system.
#
# EDGE CASE: busybox 'halt' in the d-i environment may not trigger a real
# ACPI poweroff, leaving VirtualBox in VMState="running" with 0% CPU
# ("System halted" on screen). We detect this by checking CPU load:
# if the VM's CPU usage drops to 0% for consecutive checks, it's halted.
wait_for_install() {
    local timeout=2400  # 40 minutes max (installs can be slow on shared storage)
    local elapsed=0
    local zero_cpu_count=0  # consecutive checks with ~0% CPU
    local min_elapsed=600   # don't check CPU in first 10 min (install is busy)
    local metrics_available=false

    # Try to enable metrics (not all VBox installations support this)
    if VBoxManage metrics setup --period 5 --samples 3 "${VM_NAME}" 2>/dev/null; then
        VBoxManage metrics enable "${VM_NAME}" CPU/Load/User 2>/dev/null && metrics_available=true
    fi

    while [ $elapsed -lt $timeout ]; do
        sleep 10
        elapsed=$((elapsed + 10))
        local state
        state=$(VBoxManage showvminfo "${VM_NAME}" --machinereadable 2>/dev/null \
            | grep "^VMState=" | cut -d'"' -f2)

        # Clean poweroff detected — the installer finished and ACPI worked
        if [ "$state" = "poweroff" ] || [ "$state" = "aborted" ]; then
            return 0
        fi

        # Only attempt CPU-based halt detection if metrics are ACTUALLY working
        # Without real metrics we CANNOT distinguish "install busy" from "halted"
        # so we just wait for the VM to reach poweroff state on its own.
        if [ "$state" = "running" ] && [ $elapsed -gt $min_elapsed ] && [ "$metrics_available" = true ]; then
            local cpu_pct
            cpu_pct=$(VBoxManage metrics query "${VM_NAME}" CPU/Load/User 2>/dev/null \
                | tail -1 | awk '{print $NF}' | tr -d '%' | cut -d. -f1)
            # Only count as zero if we actually got a numeric response
            if [ -n "$cpu_pct" ] && [ "$cpu_pct" -eq 0 ] 2>/dev/null; then
                zero_cpu_count=$((zero_cpu_count + 1))
            elif [ -n "$cpu_pct" ]; then
                zero_cpu_count=0
            fi
            # Require 12 consecutive zero-CPU checks (120s of true 0% CPU)
            if [ $zero_cpu_count -ge 12 ]; then
                STEP_DETAIL[3]="VM halted (0% CPU for 2min), forcing poweroff..."
                draw_dashboard
                VBoxManage controlvm "${VM_NAME}" poweroff 2>/dev/null || true
                wait_for_vm_unlock
                return 0
            fi
        fi

        # Update dashboard with elapsed time
        local mins=$((elapsed / 60))
        local secs=$((elapsed % 60))
        STEP_DETAIL[3]="installing... ${mins}m${secs}s"
        draw_dashboard
    done
    # Timeout — force poweroff as last resort
    STEP_DETAIL[3]="timeout reached, forcing poweroff..."
    draw_dashboard
    VBoxManage controlvm "${VM_NAME}" poweroff 2>/dev/null || true
    wait_for_vm_unlock
    return 0
}

# Only wait if the VM was just started for installation (DVD boot)
BOOT1=$(VBoxManage showvminfo "${VM_NAME}" --machinereadable 2>/dev/null \
    | grep "^boot1=" | cut -d'"' -f2)
if [ "$BOOT1" = "dvd" ]; then
    STEP_DETAIL[3]="installing (this takes ~10-20 min)..."
    draw_dashboard
    wait_for_install

    # ── CRITICAL: switch boot order to disk ──────────────────────────────
    # Must wait for the VM lock to release before modifyvm will work.
    STEP_DETAIL[3]="switching boot to disk..."
    draw_dashboard
    wait_for_vm_unlock
    switch_boot_to_disk

    # Verify the switch actually worked
    new_boot=$(VBoxManage showvminfo "${VM_NAME}" --machinereadable 2>/dev/null \
        | grep "^boot1=" | cut -d'"' -f2)
    if [ "$new_boot" != "disk" ]; then
        # Emergency fallback: force it one more time
        sleep 5
        switch_boot_to_disk
    fi

    STEP_DETAIL[3]="install done, booting from disk..."
    draw_dashboard
    sleep 2
    # Start VM from disk
    VBoxManage startvm "${VM_NAME}" --type gui 2>/dev/null || true
    STEP_STATUS[3]="done"; STEP_DETAIL[3]="booted from disk ✓"
    draw_dashboard
else
    STEP_DETAIL[3]="booted from disk"
    draw_dashboard
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
P_FRONTEND=$(get_vm_port frontend)
P_BACKEND=$(get_vm_port backend)
P_PRESEED=$(find_free_port 8080)

# ── Host-side SSH config (keepalives + VM shortcut) ──────────────────────────
setup_host_ssh_config() {
    local ssh_dir="$HOME/.ssh"
    local ssh_config="$ssh_dir/config"
    local marker="# Born2beRoot VM (auto-generated)"

    mkdir -p "$ssh_dir"
    chmod 700 "$ssh_dir"
    touch "$ssh_config"
    chmod 600 "$ssh_config"

    # Remove any previous Born2beRoot block
    if grep -q "$marker" "$ssh_config" 2>/dev/null; then
        sed -i "/${marker}/,/^$/d" "$ssh_config"
    fi

    # Ensure global keepalive defaults exist at the top
    # ServerAliveInterval 15 = send keepalive every 15 seconds to keep VirtualBox NAT alive
    if ! grep -q '^Host \*' "$ssh_config" 2>/dev/null; then
        cat >> "$ssh_config" << SSHEOF

Host *
    ServerAliveInterval 15
    ServerAliveCountMax 4
    TCPKeepAlive yes
    ConnectionAttempts 3
    ConnectTimeout 15
SSHEOF
    fi

    # Add VM-specific shortcut
    cat >> "$ssh_config" << SSHEOF

${marker}
Host b2b vm born2beroot
    HostName 127.0.0.1
    Port ${P_SSH}
    User dlesieur
    ServerAliveInterval 15
    ServerAliveCountMax 6
    TCPKeepAlive yes
    ConnectionAttempts 5
    ConnectTimeout 15
    StrictHostKeyChecking no
    UserKnownHostsFile /dev/null
    LogLevel ERROR

SSHEOF
    echo "  ✓ Host SSH config updated (~/.ssh/config)"
    echo "    → 'ssh b2b' connects directly to the VM"
}

setup_host_ssh_config 2>/dev/null || true

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
row "    ${DIM}SSH${RST}        ${BLD}ssh b2b${RST}   ${DIM}(shortcut — auto-configured)${RST}"
row "    ${DIM}or${RST}         ${BLD}ssh -p ${P_SSH} dlesieur@127.0.0.1${RST}"
row "    ${DIM}WordPress${RST}  ${BLD}http://127.0.0.1:${P_HTTP}/wordpress${RST}"
blank
mid
row "  ${BLD}${WHT}▸ tmux — Session Persistence${RST}"
row "    ${GRN}Auto-enabled:${RST} SSH login auto-attaches to tmux"
row "    ${DIM}If SSH drops, just reconnect — your session is still there${RST}"
row "    ${DIM}Detach:${RST}  ${BLD}Ctrl+B d${RST}     ${DIM}Reattach:${RST}  ${BLD}ssh b2b${RST}  ${DIM}(automatic)${RST}"
row "    ${DIM}Split H:${RST} ${BLD}Ctrl+B |${RST}     ${DIM}Split V:${RST}   ${BLD}Ctrl+B -${RST}"
row "    ${DIM}New win:${RST} ${BLD}Ctrl+B c${RST}     ${DIM}List:${RST}      ${BLD}tmux ls${RST}"
blank
mid
row "  ${BLD}${WHT}▸ Vite Gourmand (Dev Servers)${RST}"
row "    ${DIM}🖥️  Frontend${RST}  ${BLD}http://127.0.0.1:${P_FRONTEND}${RST}"
row "    ${DIM}🔧 Backend${RST}   ${BLD}http://127.0.0.1:${P_BACKEND}/api${RST}"
row "    ${DIM}📚 API Docs${RST}  ${BLD}http://127.0.0.1:${P_BACKEND}/api/docs${RST}"
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
row "    ${DIM}SSH${RST}      ${WHT}:${P_SSH}${RST}    ${DIM}HTTP${RST}     ${WHT}:${P_HTTP}${RST}    ${DIM}HTTPS${RST}    ${WHT}:${P_HTTPS}${RST}"
row "    ${DIM}Frontend${RST} ${WHT}:${P_FRONTEND}${RST}  ${DIM}Backend${RST}  ${WHT}:${P_BACKEND}${RST}  ${DIM}Docker${RST}   ${WHT}:${P_DOCKER}${RST}"
row "    ${DIM}MariaDB${RST}  ${WHT}:${P_MARIADB}${RST}  ${DIM}Redis${RST}    ${WHT}:${P_REDIS}${RST}"
blank
mid
row "  ${BLD}${WHT}▸ Useful Commands${RST}"
row "    ${BLU}make status${RST}      check current state"
row "    ${BLU}make poweroff${RST}    shut down the VM"
row "    ${BLU}make re${RST}          destroy and rebuild"
blank
bot
printf "\n"
