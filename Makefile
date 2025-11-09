# This Makefile has for purpose to simplify the use of those script bash, and automate from commands all the necessary steps

# =========@@ Config @@============
GEN_DEB ?= ./setup/install/vms/install_vm_debian.sh
VM_NAME ?= debian
NEW_ISO ?=
ISO_DFT := debian-13.1.0-amd64-preseed.iso
RM = rm -rf
VMS_ISO_TAR := vms_iso.tar
# =========@@ Target @@============

all: $(ISO_DFT) setup_vm start_vm

start_vm: setup_vm
	VBoxManage startvm $(VM_NAME) || echo "error"

$(ISO_DFT):
	@if ! tar -tf $(VMS_ISO_TAR) | grep -Fxq "$(ISO_DFT)"; then \
		echo "Error: $(ISO_DFT) not found in $(VMS_ISO_TAR)"; \
		exit 1; \
	else \
		echo "$(ISO_DFT) found in $(VMS_ISO_TAR), extracting..."; \
		tar -xvf $(VMS_ISO_TAR) $(ISO_DFT); \
	fi

setup_vm:
	@bash $(GEN_DEB)|| echo "error"
	
list_vms_iso:
	@tar -tf $(VMS_ISO_TAR) | grep -v '^$(VMS_ISO_TAR)$$'

rm_disk_image:
	@VBoxManage unregistervm $(VM_NAME) --delete

list_vms:
	@VBoxManage list vms

prune_vms:
	for vm in $$(VBoxManage list vms | awk '{print $1}' | tr -d '"'); do \
		VBoxManage unregistervm $$vm --delete;	\
	done

help:
	@printf "%-30.15s => %-15s\n" "all" "Create and start the VM"
	@printf "%-30.15s => %-15s\n" "start_vm" "Start the VM"
	@printf "%-30.15s => %-15s\n" "list_vms_iso" "List files in vms_iso.tar"
	@printf "%-30.15s => %-15s\n" "rm_disk_image" "Remove the VM disk image"
	@printf "%-30.15s => %-15s\n" "list_vms" "List all VMs"
	@printf "%-30.15s => %-15s\n" "prune_vms" "Remove all VMs"

extract_isos:
	@tar -xvf $(VMS_ISO_TAR)

push_iso:
	@tar -rf $(VMS_ISO_TAR) $(NEW_ISO)

pop_iso:
	@tar --exclude=$(NEW_ISO) -cf tmp_$(VMS_ISO_TAR) $(VMS_ISO_TAR) && \
	mv tmp_$(VMS_ISO_TAR) $(VMS_ISO_TAR)

clean:
	$(RM) $(ISO_DFT)

fclean: clean
	$(RM) $(VMS_ISO_TAR)

.PHONY: all start_vm list_vms_iso rm_disk_image list_vms prune_vms help