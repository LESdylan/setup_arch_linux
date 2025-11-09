#!/bin/bash

# Variables - using your specific path
VM_NAME="debian"
VM_PATH="$(pwd)/disk_images"
ISO_PATH="$(pwd)/debian-13.1.0-amd64-preseed.iso"
VM_DISK_PATH="$VM_PATH/$VM_NAME/$VM_NAME.vdi"
VM_DISK_SIZE=8192  # 8GB in MB
PRESEED_PATH="$(pwd)/preseeds/deb_preseed.cfg"

# Create VM folders if they don't exist
mkdir -p "$VM_PATH/$VM_NAME"

# Function to print headers
print_header() {
    echo "================"
    echo "      $1"
    echo "================"
}

print_header "Setting up Born2beRoot VirtualBox VM"

# Debug information for troubleshooting
print_header "DEBUG INFO"
echo "Checking for existing VMs:"
VBoxManage list vms

# Fixed VM existence check with improved error handling
VM_EXISTS=""
if VBoxManage list vms | grep -q "\"$VM_NAME\""; then
    VM_EXISTS="yes"
    print_header "VM already exists - Keeping existing configuration"
    echo "If this is incorrect, you can remove the VM with:"
    echo "VBoxManage unregistervm \"$VM_NAME\" --delete"
    exit 0
else
    print_header "Creating new VM - No existing VM found"
fi

# Check if ISO exists and allow user to update it
while [ ! -f "$ISO_PATH" ]; do
    print_header "ERROR: ISO not found at $ISO_PATH"
    read -p "Enter correct path to Debian ISO: " ISO_PATH
done

# Create the VM
print_header "Creating VirtualBox VM"
VBoxManage createvm --name "$VM_NAME" --ostype "Debian_64" --basefolder "$VM_PATH" --register || {
    echo "Failed to create VM"; exit 1;
}

# Set memory and network
print_header "Configuring VM settings"
VBoxManage modifyvm "$VM_NAME" --memory 1024 --vram 128 --cpus 1 || {
    echo "Failed to set VM memory/CPU"; exit 1;
}
VBoxManage modifyvm "$VM_NAME" --nic1 nat || {
    echo "Failed to set VM network"; exit 1;
}

# Create disk if it does not exist
if [ ! -f "$VM_DISK_PATH" ]; then
    print_header "Creating virtual disk"
    VBoxManage createmedium disk --filename "$VM_DISK_PATH" --size "$VM_DISK_SIZE" || {
        echo "Failed to create virtual disk"; exit 1;
    }
else
    print_header "Virtual disk already exists - Keeping existing disk"
fi

# Add controllers and attach devices
print_header "Setting up storage controllers"
VBoxManage storagectl "$VM_NAME" --name "SATA Controller" --add sata --controller IntelAHCI || {
    echo "Failed to add SATA controller"; exit 1;
}
VBoxManage storageattach "$VM_NAME" --storagectl "SATA Controller" --port 0 --device 0 --type hdd --medium "$VM_DISK_PATH" || {
    echo "Failed to attach virtual disk"; exit 1;
}

VBoxManage storagectl "$VM_NAME" --name "IDE Controller" --add ide || {
    echo "Failed to add IDE controller"; exit 1;
}
VBoxManage storageattach "$VM_NAME" --storagectl "IDE Controller" --port 0 --device 0 --type dvddrive --medium "$ISO_PATH" || {
    echo "Failed to attach ISO"; exit 1;
}

# Set boot order
VBoxManage modifyvm "$VM_NAME" --boot1 dvd --boot2 disk --boot3 none --boot4 none || {
    echo "Failed to set boot order"; exit 1;
}

print_header "VM Setup Complete"
echo "Your VM has been created successfully!"
echo "Start the VM with: VBoxManage startvm \"$VM_NAME\""

# Instructions for manual preseed
if [ -f "$PRESEED_PATH" ]; then
    echo ""
    echo "NOTE: To use your preseed file for automated installation:"
    echo "1. Start a local web server to serve your preseed file:"
    echo "   python -m http.server"
    echo ""
    echo "2. When the Debian installer starts, press ESC and enter boot parameters:"
    echo "   auto url=http://YOUR_IP:8000/preseed.cfg"
    echo ""
else
    echo ""
    echo "NOTE: For automated installation, create a preseed.cfg file."
fi