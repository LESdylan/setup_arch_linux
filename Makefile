# ============================================================================ #
#   Born2beRoot — One-command VM provisioner                                   #
#                                                                              #
#   Usage:                                                                     #
#     make            — full pipeline (install deps → ISO → VM → start)        #
#     make status     — show current state of every prerequisite                #
#     make start_vm   — start an already-created VM                            #
#     make poweroff   — gracefully shut down the VM                            #
#     make clean      — delete downloaded ISOs                                 #
#     make fclean     — delete ISOs + VM disk images                           #
#     make re         — fclean + full rebuild                                  #
#     make help       — list all targets                                       #
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
	@bash -c '\
	printf "$(C_BOLD)$(C_CYAN)──── Environment Status ────$(C_RESET)\n\n"; \
	\
	printf "  VirtualBox ........... "; \
	if command -v VBoxManage >/dev/null 2>&1; then \
		printf "$(C_GREEN)✓ $$(VBoxManage --version 2>/dev/null)$(C_RESET)\n"; \
	else \
		printf "$(C_RED)✗ not installed$(C_RESET)\n"; \
	fi; \
	\
	printf "  xorriso .............. "; \
	if command -v xorriso >/dev/null 2>&1; then \
		printf "$(C_GREEN)✓$(C_RESET)\n"; \
	else \
		printf "$(C_RED)✗ not installed$(C_RESET)\n"; \
	fi; \
	\
	printf "  curl ................. "; \
	if command -v curl >/dev/null 2>&1; then \
		printf "$(C_GREEN)✓$(C_RESET)\n"; \
	else \
		printf "$(C_RED)✗ not installed$(C_RESET)\n"; \
	fi; \
	\
	printf "  Preseed file ......... "; \
	if [ -f "$(PRESEED_FILE)" ]; then \
		printf "$(C_GREEN)✓ $(PRESEED_FILE)$(C_RESET)\n"; \
	else \
		printf "$(C_RED)✗ missing$(C_RESET)\n"; \
	fi; \
	\
	printf "  Debian base ISO ...... "; \
	BASE=$$(ls -1 debian-*-amd64-netinst.iso 2>/dev/null | head -n1); \
	if [ -n "$$BASE" ]; then \
		printf "$(C_GREEN)✓ $$BASE$(C_RESET)\n"; \
	else \
		printf "$(C_YELLOW)⚠ not downloaded yet$(C_RESET)\n"; \
	fi; \
	\
	printf "  Preseeded ISO ........ "; \
	PISO=$$(ls -1 debian-*-amd64-*preseed.iso 2>/dev/null | head -n1); \
	if [ -n "$$PISO" ]; then \
		printf "$(C_GREEN)✓ $$PISO$(C_RESET)\n"; \
	else \
		printf "$(C_YELLOW)⚠ not built yet$(C_RESET)\n"; \
	fi; \
	\
	printf "  VM \"$(VM_NAME)\" ............ "; \
	if VBoxManage showvminfo "$(VM_NAME)" >/dev/null 2>&1; then \
		STATE=$$(VBoxManage showvminfo "$(VM_NAME)" --machinereadable 2>/dev/null | grep "^VMState=" | cut -d\" -f2); \
		printf "$(C_GREEN)✓ ($$STATE)$(C_RESET)\n"; \
	else \
		printf "$(C_YELLOW)⚠ not created$(C_RESET)\n"; \
	fi; \
	printf "\n"'

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
	$(RM) debian-*-amd64-netinst.iso debian-*-amd64-*preseed.iso debian_iso_extract

fclean: clean rm_disk_image
	$(RM) $(DISK_DIR)

re: fclean all

# =========@@ Help @@==========================================================
help:
	@printf "$(C_BOLD)$(C_CYAN)Born2beRoot Makefile$(C_RESET)\n\n"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make / make all"  "Full pipeline: deps → ISO → VM → start"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make status"      "Show current state of all prerequisites"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make deps"        "Install VirtualBox + tools (cross-distro)"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make gen_iso"     "Download Debian ISO & inject preseed"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make setup_vm"    "Create the VirtualBox VM"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make start_vm"    "Start the VM (GUI)"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make bstart_vm"   "Start headless + unlock encrypted disk"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make poweroff"    "Shut down the VM"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make list_vms"    "List all VirtualBox VMs"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make rm_disk_image" "Delete the VM completely"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make prune_vms"   "Delete ALL VMs"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make clean"       "Remove downloaded ISOs"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make fclean"      "Remove ISOs + disk images"
	@printf "  $(C_BOLD)%-18s$(C_RESET) %s\n" "make re"          "Full clean rebuild"
	@printf "\n"
