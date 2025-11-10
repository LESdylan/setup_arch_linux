# This Makefile has for purpose to simplify the use of those script bash, and automate from commands all the necessary steps

# =========@@ Config @@============
GEN_DEB ?= ./setup/install/vms/install_vm_debian.sh
VM_NAME ?= debian
NEW_ISO ?=
ISO_DFT := $(wildcard debian-*-amd64-*.iso)
RM = rm -rf
VMS_ISO_TAR := vms_iso.tar

# =========@@ Target @@============

all: gen_iso setup_vm start_vm

start_vm: setup_vm
	VBoxManage startvm $(VM_NAME) || echo "error"

gen_iso:
	sudo bash generate/create_custom_iso.sh

setup_vm:
	@bash $(GEN_DEB)|| echo "error"

list_vms_iso:
	@tar -tf $(VMS_ISO_TAR) | grep -v '^$(VMS_ISO_TAR)$$'

rm_disk_image:
	@if VBoxManage list vms | grep -q '"$(VM_NAME)"'; then \
		VBoxManage unregistervm $(VM_NAME) --delete; \
	else \
		echo "VM '$(VM_NAME)' does not exist."; \
	fi

list_vms:
	@VBoxManage list vms

prune_vms:
	for vm in $$(VBoxManage list vms | awk '{print $1}' | tr -d '"'); do \
		VBoxManage unregistervm $$vm --delete;	\
	done

bstart_vm: setup_vm
	bash unlock_vm.sh > vm_boot.log 2>&1 &
	@echo "Waiting for VM to boot (see vm_boot.log for details)..."
	@until ssh -p 4242 dlesieur@127.0.0.1 exit; do \
		echo "Retrying SSH connection..."; \
		sleep 2; \
	done
	@echo "VM is ready!"


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

poweroff:
	VBoxManage controlvm $(VM_NAME) poweroff

clean:
	$(RM) $(ISO_DFT)

fclean: clean rm_disk_image
	$(RM) disk_images

.PHONY: all start_vm list_vms_iso rm_disk_image list_vms prune_vms help