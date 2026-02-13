# **************************************************************************** #
#                                                                              #
#                                                         :::      ::::::::    #
#    Makefile                                           :+:      :+:    :+:    #
#                                                     +:+ +:+         +:+      #
#    By: dlesieur <dlesieur@student.42.fr>          +#+  +:+       +#+         #
#                                                 +#+#+#+#+#+   +#+            #
#    Created: Invalid date        by ut down the       #+#    #+#              #
#    Updated: 2026/02/14 00:06:08 by dlesieur         ###   ########.fr        #
#                                                                              #
# **************************************************************************** #

# ============================================================================ #

# =========@@ Config @@=========================================================
VM_NAME      ?= debian
VM_SCRIPT    := ./setup/install/vms/install_vm_debian.sh
ISO_BUILDER  := ./generate/create_custom_iso.sh
PRESEED_FILE := preseeds/preseed.cfg
DISK_DIR     := disk_images
RM           := rm -rf
VMS_ISO_TAR  := vms_iso.tar

# Colours (portable — works in bash/dash/zsh)
C_RESET  := \033[0m
C_BOLD   := \033[1m
C_GREEN  := \033[32m
C_YELLOW := \033[33m
C_BLUE   := \033[34m
C_RED    := \033[31m
C_CYAN   := \033[36m

# =========@@ Main target @@===================================================
.PHONY: all deps gen_iso setup_vm start_vm status help \
        clean fclean re poweroff list_vms prune_vms \
        list_vms_iso extract_isos push_iso pop_iso rm_disk_image bstart_vm

all:
	@bash generate/orchestrate.sh "$(VM_NAME)" "$(MAKE)"

# =========@@ Install VirtualBox (cross-distro) @@=============================
deps:
	@bash -c '\
	set -e; \
	if command -v VBoxManage >/dev/null 2>&1; then \
		printf "$(C_GREEN)✓$(C_RESET) VirtualBox already installed\n"; \
		exit 0; \
	fi; \
	printf "$(C_YELLOW)Installing VirtualBox...$(C_RESET)\n"; \
	if command -v apt-get >/dev/null 2>&1; then \
		sudo apt-get update -qq && sudo apt-get install -y virtualbox; \
	elif command -v dnf >/dev/null 2>&1; then \
		sudo dnf install -y VirtualBox; \
	elif command -v yum >/dev/null 2>&1; then \
		sudo yum install -y VirtualBox; \
	elif command -v pacman >/dev/null 2>&1; then \
		sudo pacman -Sy --noconfirm virtualbox virtualbox-host-modules-arch; \
	elif command -v zypper >/dev/null 2>&1; then \
		sudo zypper install -y virtualbox; \
	elif command -v brew >/dev/null 2>&1; then \
		brew install --cask virtualbox; \
	else \
		printf "$(C_RED)✗ Cannot detect package manager. Install VirtualBox manually.$(C_RESET)\n"; \
		exit 1; \
	fi; \
	for tool in xorriso curl; do \
		if ! command -v $$tool >/dev/null 2>&1; then \
			printf "$(C_YELLOW)Installing $$tool...$(C_RESET)\n"; \
			if   command -v apt-get >/dev/null 2>&1; then sudo apt-get install -y $$tool; \
			elif command -v dnf     >/dev/null 2>&1; then sudo dnf install -y $$tool; \
			elif command -v pacman  >/dev/null 2>&1; then sudo pacman -Sy --noconfirm $$tool; \
			elif command -v zypper  >/dev/null 2>&1; then sudo zypper install -y $$tool; \
			elif command -v brew    >/dev/null 2>&1; then brew install $$tool; \
			fi; \
		fi; \
	done; \
	printf "$(C_GREEN)✓$(C_RESET) Dependencies installed\n"

# =========@@ Build preseeded ISO @@============================================
gen_iso:
	@bash $(ISO_BUILDER)

# =========@@ Create the VM @@==================================================
setup_vm:
	@bash $(VM_SCRIPT)

# =========@@ Start an existing VM @@===========================================
start_vm:
	@bash -c '\
	if ! VBoxManage showvminfo "$(VM_NAME)" >/dev/null 2>&1; then \
		printf "$(C_RED)✗$(C_RESET) VM \"$(VM_NAME)\" does not exist. Run: make setup_vm\n"; \
		exit 1; \
	fi; \
	VM_STATE=$$(VBoxManage showvminfo "$(VM_NAME)" --machinereadable 2>/dev/null | grep "^VMState=" | cut -d\" -f2); \
	if [ "$$VM_STATE" = "running" ]; then \
		printf "$(C_GREEN)✓$(C_RESET) VM is already running\n"; \
	else \
		VBoxManage startvm "$(VM_NAME)" --type gui; \
	fi'

# =========@@ Status @@========================================================
status:
	@bash generate/status.sh "$(VM_NAME)" "$(PRESEED_FILE)"

# =========@@ Headless boot with unlock @@======================================
bstart_vm:
	@bash -c '\
	if ! VBoxManage showvminfo "$(VM_NAME)" >/dev/null 2>&1; then \
		$(MAKE) --no-print-directory setup_vm; \
	fi; \
	bash unlock_vm.sh > vm_boot.log 2>&1 & \
	printf "Waiting for VM to boot (see vm_boot.log)...\n"; \
	for i in $$(seq 1 30); do \
		if ssh -o ConnectTimeout=2 -o StrictHostKeyChecking=no -p 4242 dlesieur@127.0.0.1 exit 2>/dev/null; then \
			printf "$(C_GREEN)✓ VM is ready!$(C_RESET)\n"; \
			exit 0; \
		fi; \
		printf "."; \
		sleep 2; \
	done; \
	printf "\n$(C_YELLOW)⚠ SSH not responding yet — VM may still be booting$(C_RESET)\n"'

# =========@@ Power off @@=====================================================
poweroff:
	@VBoxManage controlvm $(VM_NAME) acpipowerbutton 2>/dev/null || \
	 VBoxManage controlvm $(VM_NAME) poweroff 2>/dev/null || \
	 printf "$(C_YELLOW)VM is not running$(C_RESET)\n"

# =========@@ Listing / archive helpers @@=====================================
list_vms:
	@VBoxManage list vms 2>/dev/null || echo "No VMs found"

list_vms_iso:
	@tar -tf $(VMS_ISO_TAR) 2>/dev/null || echo "No ISO archive found"

extract_isos:
	@tar -xvf $(VMS_ISO_TAR)

push_iso:
	@tar -rf $(VMS_ISO_TAR) $(NEW_ISO)

pop_iso:
	@tar --exclude=$(NEW_ISO) -cf tmp_$(VMS_ISO_TAR) $(VMS_ISO_TAR) && \
	 mv tmp_$(VMS_ISO_TAR) $(VMS_ISO_TAR)

# =========@@ Destroy helpers @@===============================================
rm_disk_image:
	@if VBoxManage showvminfo "$(VM_NAME)" >/dev/null 2>&1; then \
		VBoxManage unregistervm "$(VM_NAME)" --delete 2>/dev/null; \
		printf "$(C_GREEN)✓$(C_RESET) VM \"$(VM_NAME)\" removed\n"; \
	else \
		echo "VM '$(VM_NAME)' does not exist."; \
	fi

prune_vms:
	@for vm in $$(VBoxManage list vms 2>/dev/null | awk '{print $$1}' | tr -d '"'); do \
		VBoxManage unregistervm "$$vm" --delete 2>/dev/null; \
	done; \
	printf "$(C_GREEN)✓$(C_RESET) All VMs removed\n"

clean:
	@chmod -R u+w debian_iso_extract 2>/dev/null || true
	$(RM) debian-*-amd64-netinst.iso debian-*-amd64-*preseed.iso debian_iso_extract

fclean: clean rm_disk_image
	$(RM) $(DISK_DIR)

re: fclean all

# =========@@ Help @@==========================================================
help:
	@bash generate/help.sh
