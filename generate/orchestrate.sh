#!/bin/bash
# Born2beRoot — main orchestrator
# Called by: make all
set -e

# Colours
RST='\033[0m'; BLD='\033[1m'; GRN='\033[32m'; YLW='\033[33m'
RED='\033[31m'; CYN='\033[36m'

VM_NAME="${1:-debian}"
MAKE_CMD="${2:-make}"

printf "${BLD}${CYN}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║           Born2beRoot — Automated VM Provisioner            ║"
echo "╚══════════════════════════════════════════════════════════════╝"
printf "${RST}\n"

# Step 1 — VirtualBox
printf "${BLD}[1/4]${RST} Checking VirtualBox...\n"
if command -v VBoxManage >/dev/null 2>&1; then
    VBOX_VER=$(VBoxManage --version 2>/dev/null)
    printf "  ${GRN}✓${RST} VirtualBox ${VBOX_VER} is installed\n"
else
    printf "  ${YLW}⚠${RST} VirtualBox not found — installing...\n"
    ${MAKE_CMD} --no-print-directory deps
fi

# Step 2 — Preseeded ISO
printf "\n${BLD}[2/4]${RST} Checking preseeded ISO...\n"
PRESEED_ISO=$(ls -1 debian-*-amd64-*preseed.iso 2>/dev/null | head -n1)
if [ -n "$PRESEED_ISO" ]; then
    printf "  ${GRN}\u2713${RST} Preseeded ISO found: ${PRESEED_ISO}\n"
else
    printf "  ${YLW}\u26a0${RST} No preseeded ISO — building one now...\n"
    ${MAKE_CMD} --no-print-directory gen_iso
    PRESEED_ISO=$(ls -1 debian-*-amd64-*preseed.iso 2>/dev/null | head -n1)
fi

# Step 3 — VM exists?
printf "\n${BLD}[3/4]${RST} Checking VM \"${VM_NAME}\"...\n"
if VBoxManage showvminfo "${VM_NAME}" >/dev/null 2>&1; then
    printf "  ${GRN}✓${RST} VM \"${VM_NAME}\" already exists\n"
else
    printf "  ${YLW}⚠${RST} VM not found — creating it now...\n"
    ${MAKE_CMD} --no-print-directory setup_vm
fi

# Step 4 — Start
printf "\n${BLD}[4/4]${RST} Starting VM...\n"
VM_STATE=$(VBoxManage showvminfo "${VM_NAME}" --machinereadable 2>/dev/null \
    | grep "^VMState=" | cut -d'"' -f2)
if [ "$VM_STATE" = "running" ]; then
    printf "  ${GRN}✓${RST} VM is already running\n"
else
    if VBoxManage startvm "${VM_NAME}" --type gui; then
        printf "  ${GRN}✓${RST} VM started successfully\n"
    else
        printf "  ${RED}✗${RST} Failed to start VM\n"
    fi
fi

# Summary
printf "\n${BLD}${CYN}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                     ✓  All Done!                            ║"
echo "╠══════════════════════════════════════════════════════════════╣"
printf "${RST}"
printf "${BLD}  What happens next:${RST}\n"
echo ""
echo "  The VM will boot from the preseeded ISO and install Debian"
echo "  fully automatically (partitioning, users, SSH, WordPress)."
echo ""
printf "${BLD}  Credentials (set by preseed):${RST}\n"
echo "    root password ......... temproot123"
echo "    user (dlesieur) ....... tempuser123"
echo "    disk encryption ....... tempencrypt123"
echo ""
printf "${BLD}  After installation finishes & VM reboots:${RST}\n"
echo "    1. Enter disk encryption passphrase: tempencrypt123"
echo "    2. Log in as dlesieur / tempuser123"
echo "    3. SSH from host:  ssh -p 4242 dlesieur@127.0.0.1"
echo "    4. WordPress:      http://127.0.0.1:8080/wordpress"
echo ""
printf "${BLD}  Useful make targets:${RST}\n"
echo "    make status    — check current state"
echo "    make poweroff  — shut down the VM"
echo "    make re        — destroy everything and rebuild"
printf "${CYN}"
echo "╚══════════════════════════════════════════════════════════════╝"
printf "${RST}\n"
