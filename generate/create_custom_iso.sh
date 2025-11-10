#!/bin/bash

set -e  # Exit on any error

ISO_DIR="debian_iso_extract"
ISO_FILENAME="debian-13.1.0-amd64-netinst.iso"
URL_IMAGE_ISO="https://cdimage.debian.org/debian-cd/current/amd64/iso-cd/$ISO_FILENAME"
PRESEED_FILE="preseeds/preseed.cfg"
OUTPUT_ISO="debian-13.1.0-amd64-preseed.iso"

echo "===== Creating Custom Debian ISO with Preseed ====="

# Check if ISO already exists locally
if [ -f "$ISO_FILENAME" ]; then
    echo "✓ ISO file found locally: $ISO_FILENAME"
else
    echo "Downloading ISO from $URL_IMAGE_ISO..."
    wget "$URL_IMAGE_ISO" || { echo "Error: Failed to download ISO"; exit 1; }
fi

# Check if preseed file exists
if [ ! -f "$PRESEED_FILE" ]; then
    echo "Error: $PRESEED_FILE not found!"
    exit 1
fi

# Create extraction directory
echo "Extracting ISO to $ISO_DIR..."
rm -rf "$ISO_DIR"
mkdir -p "$ISO_DIR"
bsdtar -C "$ISO_DIR" -xf "$ISO_FILENAME"

# Make extracted files writable
chmod -R u+w "$ISO_DIR"

# Copy preseed file to ISO root
echo "Copying preseed file..."
cp "$PRESEED_FILE" "$ISO_DIR/preseed.cfg"

# Edit boot menu for BIOS (ISOLINUX)
echo "Updating BIOS boot menu (isolinux)..."
ISOLINUX_CFG="$ISO_DIR/isolinux/txt.cfg"
if [ -f "$ISOLINUX_CFG" ]; then
    cat > "$ISOLINUX_CFG" << 'EOF'
default install
label install
    menu label ^Install
    kernel /install.amd/vmlinuz
    append auto=true priority=critical preseed/file=/cdrom/preseed.cfg vga=788 initrd=/install.amd/initrd.gz --- quiet
EOF
    echo "✓ BIOS boot menu updated"
else
    echo "Warning: $ISOLINUX_CFG not found"
fi

# Edit boot menu for EFI (GRUB)
echo "Updating EFI boot menu (GRUB)..."
GRUB_CFG="$ISO_DIR/boot/grub/grub.cfg"
if [ -f "$GRUB_CFG" ]; then
    # Backup original
    cp "$GRUB_CFG" "$GRUB_CFG.bak"
    
    # Create new GRUB config with auto-install as default
    cat > "$GRUB_CFG" << 'GRUBEOF'
set default=0
set timeout=1

menuentry 'Automated Install' {
    set background_color=black
    linux    /install.amd/vmlinuz auto=true priority=critical preseed/file=/cdrom/preseed.cfg vga=788 --- quiet
    initrd   /install.amd/initrd.gz
}

menuentry 'Install' {
    set background_color=black
    linux    /install.amd/vmlinuz vga=788 --- quiet
    initrd   /install.amd/initrd.gz
}
GRUBEOF
    
    echo "✓ EFI boot menu updated"
else
    echo "Warning: $GRUB_CFG not found"
fi

# Update MD5 sums
echo "Updating MD5 checksums..."
cd "$ISO_DIR"
md5sum $(find -follow -type f ! -name md5sum.txt ! -path './isolinux/*') > md5sum.txt
cd ..

# Rebuild ISO
echo "Rebuilding ISO with xorriso..."
cd "$ISO_DIR"
xorriso -as mkisofs \
    -o "../$OUTPUT_ISO" \
    -c isolinux/boot.cat \
    -b isolinux/isolinux.bin \
    -no-emul-boot -boot-load-size 4 -boot-info-table \
    -eltorito-alt-boot \
    -e boot/grub/efi.img \
    -no-emul-boot \
    -isohybrid-gpt-basdat \
    -r -J \
    . || { echo "Error: Failed to create ISO"; exit 1; }
cd ..

echo "===== Success ====="
echo "✓ Custom ISO created: $OUTPUT_ISO"
echo "Use this ISO with your VirtualBox VM for automated Debian installation"

# Cleanup
rm -rf "$ISO_DIR"
echo "✓ Temporary files cleaned up"