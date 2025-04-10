#!/bin/bash

# Variables - update these as needed
VM_NAME="born2beroot"
VM_PATH="/home/dlesieur/sgoinfre/dlesieur42/m_virtual_machine"
DEBIAN_ISO="$(pwd)/debian-12.10.0-amd64-netinst.iso"
CUSTOM_ISO="$(pwd)/debian-12.10.0-auto.iso"
PRESEED_FILE="$(pwd)/preseed.cfg"
VM_DISK_PATH="$VM_PATH/$VM_NAME/$VM_NAME.vdi"
VM_DISK_SIZE=8192  # 8GB in MB

# Check if required tools are installed
for cmd in xorriso mkisofs; do
    if ! command -v $cmd &> /dev/null; then
        echo "Error: $cmd is not installed. Please install it first."
        echo "sudo apt-get install xorriso"
        exit 1
    fi
done

# Check if preseed file exists
if [ ! -f "$PRESEED_FILE" ]; then
    echo "Error: Preseed file not found at $PRESEED_FILE"
    exit 1
fi

# Check if Debian ISO exists
if [ ! -f "$DEBIAN_ISO" ]; then
    echo "Error: Debian ISO not found at $DEBIAN_ISO"
    exit 1
fi

# Create working directories
TEMP_DIR=$(mktemp -d)
cd "$TEMP_DIR"

echo "Extracting original ISO..."
mkdir iso
sudo mount -o loop "$DEBIAN_ISO" iso
mkdir -p custom/isolinux

# Copy the ISO content
echo "Copying ISO content..."
cp -rT iso custom
sudo umount iso
rmdir iso

# Add preseed file
echo "Adding preseed file..."
cp "$PRESEED_FILE" custom/preseed.cfg

# Modify isolinux configuration for automated install
echo "Modifying boot configuration..."
sed -i 's/timeout 0/timeout 1/' custom/isolinux/isolinux.cfg
sed -i 's/default installgui/default install/' custom/isolinux/isolinux.cfg
sed -i 's/prompt 0/prompt 0\ninclude preseed.cfg/' custom/isolinux/isolinux.cfg

# Modify the install menu entry to use the preseed file
sed -i '/^label install$/,/append/ s|append|append auto=true priority=critical file=/cdrom/preseed.cfg|' custom/isolinux/txt.cfg

# Create new ISO
echo "Creating custom ISO..."
xorriso -as mkisofs -r -J -joliet-long -l -b isolinux/isolinux.bin -c isolinux/boot.cat \
    -no-emul-boot -boot-load-size 4 -boot-info-table \
    -eltorito-alt-boot -e boot/grub/efi.img -no-emul-boot \
    -isohybrid-gpt-basdat -isohybrid-mbr /usr/lib/ISOLINUX/isohdpfx.bin \
    -o "$CUSTOM_ISO" custom/

echo "Custom ISO created at: $CUSTOM_ISO"

# Clean up
cd -
sudo rm -rf "$TEMP_DIR"

# Create VirtualBox VM
echo "Creating VirtualBox VM..."
# Check if VM already exists
if VBoxManage list vms | grep -q "\"$VM_NAME\""; then
    echo "VM already exists. Removing it..."
    VBoxManage unregistervm "$VM_NAME" --delete
fi

# Create VM
VBoxManage createvm --name "$VM_NAME" --ostype "Debian_64" --basefolder "$VM_PATH" --register

# Set memory and network
VBoxManage modifyvm "$VM_NAME" --memory 1024 --vram 128 --cpus 1
VBoxManage modifyvm "$VM_NAME" --nic1 nat

# Create disk
VBoxManage createmedium disk --filename "$VM_DISK_PATH" --size "$VM_DISK_SIZE"

# Add controllers and attach devices
VBoxManage storagectl "$VM_NAME" --name "SATA Controller" --add sata --controller IntelAHCI
VBoxManage storageattach "$VM_NAME" --storagectl "SATA Controller" --port 0 --device 0 --type hdd --medium "$VM_DISK_PATH"

VBoxManage storagectl "$VM_NAME" --name "IDE Controller" --add ide
VBoxManage storageattach "$VM_NAME" --storagectl "IDE Controller" --port 0 --device 0 --type dvddrive --medium "$CUSTOM_ISO"

# Set boot order
VBoxManage modifyvm "$VM_NAME" --boot1 dvd --boot2 disk --boot3 none --boot4 none

echo "Setup complete! Start the VM with: VBoxManage startvm \"$VM_NAME\""
echo "The installation will run completely automatically with no user interaction required."