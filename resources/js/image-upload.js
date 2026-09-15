export default () => ({
    previews: [],
    clearPreviews() {
        this.previews.forEach((preview) => URL.revokeObjectURL(preview.url));
        this.previews = [];
    },
    selectFiles(files) {
        this.clearPreviews();
        this.previews = [...files].map((file, fileIndex) => ({ file, fileIndex }))
            .filter(({ file }) => file.type.startsWith('image/')).map(({ file, fileIndex }) => ({
                url: URL.createObjectURL(file), name: file.name, fileIndex,
            }));
    },
    removeFile(index) {
        const transfer = new DataTransfer();
        [...this.$refs.fileInput.files].forEach((file, fileIndex) => {
            if (fileIndex !== index) transfer.items.add(file);
        });
        this.$refs.fileInput.files = transfer.files;
        this.selectFiles(transfer.files);
    },
    destroy() {
        this.clearPreviews();
    },
});
