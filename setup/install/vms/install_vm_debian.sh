#!/bin/bash

set -e  # Exit on any error

# Variables - using your specific path
VM_NAME="debian"
VM_PATH="$(pwd)/disk_images"
ISO_PATH="$(pwd)/debian-13.1.0-amd64-preseed.iso"
VM_DISK_PATH="$VM_PATH/$VM_NAME/$VM_NAME.vdi"
VM_DISK_SIZE=32000  # 32GB in MB
PRESEED_PATH="$(pwd)/preseeds/preseed.cfg"
BASE_URL="https://cdimage.debian.org/debian-cd/current/amd64/iso-cd/"
# Get only the ISO filename (strip HTML tags)
ISO_FILE=$(curl -s $BASE_URL | grep -oE 'debian-[0-9.]+-amd64-netinst\.iso' | head -n 1)
ISO_URL="${BASE_URL}${ISO_FILE}"
ISO_PATH="$HOME/Downloads/${ISO_FILE}"

# VM Configuration
VM_MEMORY=4096  # Increased for WordPress
VM_CPUS=4
VM_VRAM=128
SSH_PORT=4242
HTTP_PORT=80
HTTPS_PORT=443

DOCKER_REGISTRY_PORT=5000
MARIADB_PORT=3306
REDIS_PORT=6379

# Create VM folders if they don't exist
mkdir -p "$VM_PATH/$VM_NAME"

# Function to print headers
print_header() {
    echo ""
    echo "==============================================="
    echo "  $1"
    echo "==============================================="
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

echo "Downloading latest debian ISO: $ISO_FILE"

# Check if ISO exists and allow user to update it
if [ ! -f "$ISO_PATH" ]; then
    mkdir -p "$HOME/Downloads"
    wget -O "$ISO_PATH" "$ISO_URL"
fi

echo "ISO downloaded at $ISO_PATH"

# Create the VM
print_header "Creating VirtualBox VM"
VBoxManage createvm --name "$VM_NAME" --ostype "Debian_64" --basefolder "$VM_PATH" --register || {
    echo "Failed to create VM"; exit 1;
}

# Set memory, CPU, and display
print_header "Configuring VM hardware settings"
VBoxManage modifyvm "$VM_NAME" \
    --memory "$VM_MEMORY" \
    --vram "$VM_VRAM" \
    --cpus "$VM_CPUS" \
    --acpi on \
    --ioapic on \
    --rtcuseutc on \
    --clipboard bidirectional \
    --draganddrop bidirectional || {
    echo "Failed to set VM hardware"; exit 1;
}

# Set network - NAT with port forwarding
print_header "Configuring network and port forwarding"
VBoxManage modifyvm "$VM_NAME" --nic1 nat || {
    echo "Failed to set VM network"; exit 1;
}

# Set up NAT port forwarding for SSH (host:4242 -> guest:4242)
VBoxManage modifyvm "$VM_NAME" --natpf1 "ssh4242,tcp,,${SSH_PORT},,${SSH_PORT}" || {
    echo "Failed to set up NAT port forwarding for SSH"; exit 1;
}

# Set up NAT port forwarding for HTTP (host:8080 -> guest:80)
VBoxManage modifyvm "$VM_NAME" --natpf1 "http8080,tcp,,8080,,${HTTP_PORT}" || {
    echo "Failed to set up NAT port forwarding for HTTP"; exit 1;
}

# Set up NAT port forwarding for HTTPS (host:8443 -> guest:443)
VBoxManage modifyvm "$VM_NAME" --natpf1 "https8443,tcp,,8443,,${HTTPS_PORT}" || {
    echo "Failed to set up NAT port forwarding for HTTPS"; exit 1;
}

# Set up NAT port forwarding for Docker Registry (host:5000 -> guest:5000)
VBoxManage modifyvm "$VM_NAME" --natpf1 "docker5000,tcp,,5000,,${DOCKER_REGISTRY_PORT}" || {
    echo "Failed to set up NAT port forwarding for Docker Registry"; exit 1;
}

# Set up NAT port forwarding for MariaDB (host:3306 -> guest:3306)
VBoxManage modifyvm "$VM_NAME" --natpf1 "mariadb3306,tcp,,3306,,${MARIADB_PORT}" || {
    echo "Failed to set up NAT port forwarding for MariaDB"; exit 1;
}

# Set up NAT port forwarding for Redis (host:6379 -> guest:6379)
VBoxManage modifyvm "$VM_NAME" --natpf1 "redis6379,tcp,,6379,,${REDIS_PORT}" || {
    echo "Failed to set up NAT port forwarding for Redis"; exit 1;
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

# Set boot order (DVD first for installation, then disk)
print_header "Setting boot order"
VBoxManage modifyvm "$VM_NAME" --boot1 dvd --boot2 disk --boot3 none --boot4 none || {
    echo "Failed to set boot order"; exit 1;
}

# Enable nested virtualization (optional, for advanced use)
VBoxManage modifyvm "$VM_NAME" --nested-hw-virt on || true

print_header "VM Setup Complete"
echo ""
echo "Port Forwarding Configuration:"
echo "  - SSH:      Host 127.0.0.1:${SSH_PORT} -> Guest 0.0.0.0:${SSH_PORT}"
echo "  - HTTP:     Host 127.0.0.1:8080 -> Guest 0.0.0.0:${HTTP_PORT}"
echo "  - HTTPS:    Host 127.0.0.1:8443 -> Guest 0.0.0.0:${HTTPS_PORT}"
echo "  - Docker:   Host 127.0.0.1:5000 -> Guest 0.0.0.0:${DOCKER_REGISTRY_PORT}"
echo "  - MariaDB:  Host 127.0.0.1:3306 -> Guest 0.0.0.0:${MARIADB_PORT}"
echo "  - Redis:    Host 127.0.0.1:6379 -> Guest 0.0.0.0:${REDIS_PORT}"
echo ""
echo "Next Steps:"
echo "  1. Start the VM:"
echo "     VBoxManage startvm \"$VM_NAME\" --type headless"
echo ""
echo "  2. SSH into your VM from host:"
echo "     ssh -p ${SSH_PORT} dlesieur@127.0.0.1"
echo ""
echo "  3. Install Docker and Docker Compose:"
echo "     curl -fsSL https://get.docker.com -o get-docker.sh && sudo sh get-docker.sh"
echo "     sudo usermod -aG docker dlesieur"
echo "     sudo curl -L \"https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-\$(uname -s)-\$(uname -m)\" -o /usr/local/bin/docker-compose"
echo "     sudo chmod +x /usr/local/bin/docker-compose"
echo ""
echo "  4. Clone Inception project and run docker-compose:"
echo "     cd ~/inception && docker-compose up -d"
echo ""
echo "  5. Access services from host:"
echo "     - WordPress:      http://127.0.0.1:8080"
echo "     - MariaDB:        mysql -h 127.0.0.1 -P 3306 -u root -p"
echo "     - Docker Registry: http://127.0.0.1:5000"
echo ""