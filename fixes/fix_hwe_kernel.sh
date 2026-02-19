#!/bin/bash
# =============================================================================
# fix_hwe_kernel.sh — Safely remove HWE kernels incompatible with VirtualBox
#
# Problem: VirtualBox 7.0.x DKMS fails to build against kernel ≥ 6.13 / 7.x.
#          If such a kernel is installed (even if not booted), the broken DKMS
#          state prevents /dev/vboxdrv from loading on ANY kernel.
#
# Special case: dpkg refuses to remove the *currently running* kernel.
#               This script detects that, switches GRUB to a safe kernel, and
#               asks you to reboot. Run it again after rebooting to finish.
# =============================================================================

set -euo pipefail

# ── Colours ──────────────────────────────────────────────────────────────────
R='\033[0;31m'; Y='\033[1;33m'; G='\033[0;32m'
B='\033[0;34m'; C='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

info()    { printf "${B}▶${NC} %s\n" "$*"; }
success() { printf "${G}✓${NC} %s\n" "$*"; }
warn()    { printf "${Y}⚠${NC}  %s\n" "$*"; }
error()   { printf "${R}✗${NC} %s\n" "$*" >&2; }
die()     { error "$*"; exit 1; }
hr()      { printf '%s\n' "────────────────────────────────────────────────────"; }

# ── Root check ────────────────────────────────────────────────────────────────
if [[ "$(id -u)" -ne 0 ]]; then
    warn "Re-running with sudo..."
    exec sudo bash "$0" "$@"
fi

hr
printf "${BOLD}  VirtualBox HWE Kernel Removal Fix${NC}\n"
hr

RUNNING_KERNEL="$(uname -r)"
info "Running kernel: ${RUNNING_KERNEL}"

# ── Discover incompatible kernels ────────────────────────────────────────────
# Incompatible: linux-image-6.13+ or linux-image-7.x+
mapfile -t BAD_PKGS < <(
    dpkg -l 2>/dev/null \
        | awk '/^ii.*linux-image-[0-9]/{print $2}' \
        | grep -E 'linux-image-(6\.(1[3-9]|[2-9][0-9])\.|[7-9]\.)' \
        || true
)

if [[ "${#BAD_PKGS[@]}" -eq 0 ]]; then
    success "No incompatible HWE kernels found — nothing to do."
    exit 0
fi

info "Incompatible kernel package(s) found:"
for pkg in "${BAD_PKGS[@]}"; do
    printf "   ${Y}•${NC} %s\n" "$pkg"
done
hr

# ── Separate running vs removable ────────────────────────────────────────────
RUNNING_IN_LIST=false
REMOVE_NOW=()
for pkg in "${BAD_PKGS[@]}"; do
    ver="${pkg#linux-image-}"          # strip prefix → e.g. 6.17.0-14-generic
    if [[ "$ver" == "$RUNNING_KERNEL" ]]; then
        RUNNING_IN_LIST=true
    else
        REMOVE_NOW+=("$pkg")
    fi
done

# ── Helper: remove safe (non-running) packages ───────────────────────────────
remove_safe_packages() {
    if [[ "${#REMOVE_NOW[@]}" -eq 0 ]]; then
        return 0
    fi

    info "Removing non-running incompatible kernel(s): ${REMOVE_NOW[*]}"

    # Also remove matching headers/modules if present
    EXTRA=()
    for pkg in "${REMOVE_NOW[@]}"; do
        ver="${pkg#linux-image-}"
        for extra_pkg in "linux-headers-${ver}" "linux-modules-${ver}" "linux-modules-extra-${ver}"; do
            if dpkg -l "$extra_pkg" &>/dev/null; then
                EXTRA+=("$extra_pkg")
            fi
        done
    done

    apt-get remove -y "${REMOVE_NOW[@]}" "${EXTRA[@]}" || {
        error "apt remove failed for some packages — continuing to dpkg --configure -a"
    }

    info "Running apt autoremove..."
    apt-get autoremove -y || warn "apt autoremove encountered issues (non-fatal)"

    info "Finalising dpkg configuration..."
    dpkg --configure -a || warn "dpkg --configure -a reported issues (check output above)"

    info "Reloading VirtualBox kernel driver..."
    if modprobe vboxdrv 2>/dev/null; then
        success "vboxdrv loaded"
    else
        warn "modprobe vboxdrv failed — you may need to reinstall virtualbox-dkms"
        printf "    ${Y}Run:${NC} sudo apt install --reinstall virtualbox-dkms\n"
    fi

    success "Non-running kernels removed."
}

# ── Helper: switch GRUB default to a safe kernel ─────────────────────────────
switch_grub_to_safe_kernel() {
    # Find all installed kernels that are NOT bad
    mapfile -t ALL_IMGS < <(
        dpkg -l 2>/dev/null \
            | awk '/^ii.*linux-image-[0-9]/{print $2}' \
            | grep -v -E 'linux-image-(6\.(1[3-9]|[2-9][0-9])\.|[7-9]\.)' \
            || true
    )

    if [[ "${#ALL_IMGS[@]}" -eq 0 ]]; then
        die "No safe kernel found to switch to. Aborting — do NOT remove the current kernel manually."
    fi

    # Pick the most recent safe kernel by version sort
    SAFE_PKG="$(printf '%s\n' "${ALL_IMGS[@]}" | sort -V | tail -1)"
    SAFE_VER="${SAFE_PKG#linux-image-}"
    info "Safe kernel to switch to: ${SAFE_VER} (package: ${SAFE_PKG})"

    if [[ ! -f "/boot/vmlinuz-${SAFE_VER}" ]]; then
        warn "/boot/vmlinuz-${SAFE_VER} not found on disk — trying to install ${SAFE_PKG}..."
        apt-get install -y "$SAFE_PKG" || die "Could not install ${SAFE_PKG}"
    fi

    # Locate the GRUB menu entry title for the safe kernel
    GRUB_CFG="/boot/grub/grub.cfg"
    if [[ ! -f "$GRUB_CFG" ]]; then
        die "${GRUB_CFG} not found. Is GRUB installed?"
    fi

    # Match: menuentry 'Ubuntu, with Linux 6.8.0-100-generic' (single or double quotes)
    GRUB_ENTRY="$(
        grep -oP "(?<=menuentry ')[^']*${SAFE_VER}[^']*(?=')" "$GRUB_CFG" | head -1 \
        || grep -oP "(?<=menuentry \")[^\"]*${SAFE_VER}[^\"]*(?=\")" "$GRUB_CFG" | head -1 \
        || true
    )"

    if [[ -z "$GRUB_ENTRY" ]]; then
        warn "Could not find GRUB entry for ${SAFE_VER} in ${GRUB_CFG}."
        warn "Run 'update-grub' first then reboot and pick the kernel from the menu."
        info "Updating GRUB anyway..."
        update-grub
        return
    fi

    info "Found GRUB entry: ${GRUB_ENTRY}"

    # Ensure GRUB uses saved default
    if ! grep -q '^GRUB_DEFAULT=saved' /etc/default/grub; then
        cp /etc/default/grub /etc/default/grub.bak
        sed -i 's/^GRUB_DEFAULT=.*/GRUB_DEFAULT=saved/' /etc/default/grub
        info "Set GRUB_DEFAULT=saved in /etc/default/grub (backup: /etc/default/grub.bak)"
    fi

    grub-set-default "$GRUB_ENTRY"
    update-grub
    success "GRUB will boot into '${GRUB_ENTRY}' on next start."
}

# ── Main logic ────────────────────────────────────────────────────────────────
remove_safe_packages

if $RUNNING_IN_LIST; then
    hr
    warn "The currently running kernel (${RUNNING_KERNEL}) is incompatible."
    warn "dpkg refuses to remove it while it is running."
    hr
    info "Switching GRUB default to a safe kernel..."
    switch_grub_to_safe_kernel

    # Also remove the running kernel's headers/modules NOW (those are safe)
    RUN_PKG_PREFIX="linux-image-${RUNNING_KERNEL}"
    PURGEABLE=()
    for extra_pkg in \
        "linux-headers-${RUNNING_KERNEL}" \
        "linux-modules-${RUNNING_KERNEL}" \
        "linux-modules-extra-${RUNNING_KERNEL}"; do
        if dpkg -l "$extra_pkg" &>/dev/null 2>&1; then
            PURGEABLE+=("$extra_pkg")
        fi
    done

    if [[ "${#PURGEABLE[@]}" -gt 0 ]]; then
        info "Removing headers/modules for running kernel (safe to do now): ${PURGEABLE[*]}"
        apt-get remove -y "${PURGEABLE[@]}" || warn "Some header/module packages could not be removed"
    fi

    hr
    printf "${Y}${BOLD}ACTION REQUIRED:${NC}\n"
    printf "  Reboot into kernel ${G}$(uname -r | sed "s/${RUNNING_KERNEL}/$(dpkg -l 2>/dev/null | awk '/^ii.*linux-image-[0-9]/{print $2}' | grep -v -E 'linux-image-(6\.(1[3-9]|[2-9][0-9])\.|[7-9]\.)' | sort -V | tail -1 | sed 's/linux-image-//')/g")${NC} then run:\n\n" 2>/dev/null || true
    printf "  Reboot into the safe kernel, then run:\n\n"
    printf "    ${G}sudo bash fixes/fix_hwe_kernel.sh${NC}\n\n"
    printf "  …to complete the removal of linux-image-${RUNNING_KERNEL}.\n"
    hr

    read -r -p "$(printf "${C}Reboot now?${NC} [y/N] ")" REPLY
    if [[ "${REPLY,,}" == "y" ]]; then
        info "Rebooting in 5 seconds... (Ctrl-C to cancel)"
        sleep 5
        reboot
    else
        warn "Reboot skipped. Remember to reboot before VirtualBox will work."
    fi
else
    success "All incompatible kernels removed. VirtualBox should now work correctly."
    if test -c /dev/vboxdrv; then
        success "/dev/vboxdrv is present — ready to run 'make start_vm'"
    else
        warn "/dev/vboxdrv still missing. Try: sudo modprobe vboxdrv"
    fi
fi
