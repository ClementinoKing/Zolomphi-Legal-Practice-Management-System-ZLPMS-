import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    ChevronRight,
    Eye,
    FileImage,
    FilePlus2,
    FileSpreadsheet,
    FileText,
    Folder,
    FolderOpen,
    FolderPlus,
    Grid2X2,
    HardDrive,
    List,
    Search,
    Upload,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import type { ChangeEvent, DragEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { index as files, store } from '@/routes/files';

type DriveItemKind = 'folder' | 'document' | 'pdf' | 'spreadsheet' | 'image';

type DriveItem = {
    id: string;
    name: string;
    kind: DriveItemKind;
    parentId: string | null;
    updatedAt: string;
    owner: string;
    size?: string;
    description: string;
    downloadUrl?: string;
};

type ClientDocument = {
    id: number;
    name: string;
    originalFilename: string;
    mimeType: string | null;
    sizeBytes: number | null;
    updatedAt: string | null;
    downloadUrl: string;
};

type ClientFolder = {
    id: number;
    name: string;
    clientNumber: string;
    documentCount: number;
    documents: ClientDocument[];
};

type DriveLibrary = 'workspace' | 'client-files' | 'organization-files';

function initialDriveItems(
    clientFolders: ClientFolder[],
    organizationFolders: ClientFolder[],
): DriveItem[] {
    const driveFolderItems = (
        folders: ClientFolder[],
        rootFolderId: string,
        folderPrefix: 'client' | 'organization',
        folderLabel: string,
    ): DriveItem[] =>
        folders.flatMap((folder): DriveItem[] => {
            const folderId = `${folderPrefix}-${folder.id}`;

            return [
                {
                    id: folderId,
                    name: folder.name,
                    kind: 'folder',
                    parentId: rootFolderId,
                    updatedAt:
                        folder.documentCount > 0
                            ? `${folder.documentCount} file${folder.documentCount === 1 ? '' : 's'}`
                            : 'No files yet',
                    owner: folder.clientNumber,
                    description: `All company-drive files linked to ${folder.name}.`,
                },
                ...folder.documents.map((document): DriveItem => ({
                    id: `${folderPrefix}-document-${document.id}`,
                    name: document.name || document.originalFilename,
                    kind: itemKindFromDocument(document),
                    parentId: folderId,
                    updatedAt: document.updatedAt
                        ? `Updated ${new Intl.DateTimeFormat('en', { dateStyle: 'medium' }).format(new Date(document.updatedAt))}`
                        : 'Added previously',
                    owner: folder.name,
                    size: document.sizeBytes
                        ? formatFileSize(document.sizeBytes)
                        : undefined,
                    description: `${folderLabel} file for ${folder.name}.`,
                    downloadUrl: document.downloadUrl,
                })),
            ];
        });

    return [
        {
            id: 'client-files',
            name: 'Clients',
            kind: 'folder',
            parentId: null,
            updatedAt: `${clientFolders.length} client folders`,
            owner: 'Zolomphi & Co.',
            description:
                'One protected company-drive folder for every individual client.',
        },
        {
            id: 'organization-files',
            name: 'Organisations',
            kind: 'folder',
            parentId: null,
            updatedAt: `${organizationFolders.length} organization folders`,
            owner: 'Zolomphi & Co.',
            description:
                'One protected company-drive folder for every organization.',
        },
        ...driveFolderItems(clientFolders, 'client-files', 'client', 'Client'),
        ...driveFolderItems(
            organizationFolders,
            'organization-files',
            'organization',
            'Organization',
        ),
    ];
}

function itemIcon(kind: DriveItemKind, className?: string) {
    const iconClassName = cn('size-7', className);

    if (kind === 'folder') {
        return <Folder className={cn(iconClassName, 'text-[#EA702D]')} />;
    }

    if (kind === 'pdf') {
        return <FileText className={cn(iconClassName, 'text-red-600')} />;
    }

    if (kind === 'spreadsheet') {
        return (
            <FileSpreadsheet
                className={cn(iconClassName, 'text-emerald-600')}
            />
        );
    }

    if (kind === 'image') {
        return <FileImage className={cn(iconClassName, 'text-violet-600')} />;
    }

    return <FileText className={cn(iconClassName, 'text-blue-600')} />;
}

function itemKindFromDocument(document: ClientDocument): DriveItemKind {
    if (document.mimeType?.startsWith('image/')) {
        return 'image';
    }

    if (
        document.mimeType?.includes('spreadsheet') ||
        document.originalFilename.toLowerCase().endsWith('.xlsx') ||
        document.originalFilename.toLowerCase().endsWith('.csv')
    ) {
        return 'spreadsheet';
    }

    if (document.mimeType === 'application/pdf') {
        return 'pdf';
    }

    return 'document';
}

function formatFileSize(size: number): string {
    if (size >= 1024 * 1024 * 1024 * 1024) {
        return `${(size / (1024 * 1024 * 1024 * 1024)).toFixed(1)} TB`;
    }

    if (size >= 1024 * 1024 * 1024) {
        return `${(size / (1024 * 1024 * 1024)).toFixed(1)} GB`;
    }

    if (size < 1024 * 1024) {
        return `${Math.max(1, Math.round(size / 1024))} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function companyDriveFolderId(itemId: string | null): number | null {
    const match = itemId?.match(/^(?:client|organization)-(\d+)$/);

    return match === null || match === undefined ? null : Number(match[1]);
}

export default function FilesIndex({
    clientFolders,
    organizationFolders,
    storage,
}: {
    clientFolders: ClientFolder[];
    organizationFolders: ClientFolder[];
    storage: {
        usedBytes: number;
        quotaBytes: number;
    };
}) {
    const [items, setItems] = useState<DriveItem[]>(() =>
        initialDriveItems(clientFolders, organizationFolders),
    );
    const [activeFolderId, setActiveFolderId] = useState<string | null>(null);
    const [library, setLibrary] = useState<DriveLibrary>('workspace');
    const [search, setSearch] = useState('');
    const [view, setView] = useState<'grid' | 'list'>('grid');
    const [isFolderDialogOpen, setIsFolderDialogOpen] = useState(false);
    const [isDocumentDialogOpen, setIsDocumentDialogOpen] = useState(false);
    const [folderName, setFolderName] = useState('');
    const [documentName, setDocumentName] = useState('');
    const [previewItem, setPreviewItem] = useState<DriveItem | null>(null);
    const [draggedItemId, setDraggedItemId] = useState<string | null>(null);
    const [dropTargetId, setDropTargetId] = useState<string | null>(null);
    const [isFileDropActive, setIsFileDropActive] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [isUploading, setIsUploading] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const folderInputRef = useRef<HTMLInputElement>(null);

    const folderTrail = useMemo(() => {
        const trail: DriveItem[] = [];
        let parentId = activeFolderId;

        while (parentId) {
            const folder = items.find((item) => item.id === parentId);

            if (!folder) {
                break;
            }

            trail.unshift(folder);
            parentId = folder.parentId;
        }

        return trail;
    }, [activeFolderId, items]);

    const visibleItems = useMemo(() => {
        const normalizedSearch = search.trim().toLowerCase();

        if (normalizedSearch) {
            return items.filter((item) =>
                item.name.toLowerCase().includes(normalizedSearch),
            );
        }

        if (activeFolderId) {
            return items.filter((item) => item.parentId === activeFolderId);
        }

        if (library === 'client-files' || library === 'organization-files') {
            return items.filter((item) => item.parentId === library);
        }

        return items.filter((item) => item.parentId === null);
    }, [activeFolderId, items, library, search]);

    const folders = visibleItems.filter((item) => item.kind === 'folder');
    const fileItems = visibleItems.filter((item) => item.kind !== 'folder');
    const storageUsagePercent = Math.min(
        100,
        (storage.usedBytes / storage.quotaBytes) * 100,
    );
    const libraryTitle: Record<DriveLibrary, string> = {
        workspace: 'Company drive',
        'client-files': 'Clients',
        'organization-files': 'Organisations',
    };

    function selectLibrary(nextLibrary: DriveLibrary): void {
        setLibrary(nextLibrary);
        setSearch('');
        setActiveFolderId(
            nextLibrary === 'client-files'
                ? 'client-files'
                : nextLibrary === 'organization-files'
                  ? 'organization-files'
                  : null,
        );
    }

    function openFolder(folderId: string): void {
        setActiveFolderId(folderId);

        let currentFolder = items.find((item) => item.id === folderId);
        let folderLibrary: DriveLibrary = 'workspace';

        while (currentFolder) {
            if (currentFolder.id === 'client-files') {
                folderLibrary = 'client-files';
                break;
            }

            if (currentFolder.id === 'organization-files') {
                folderLibrary = 'organization-files';
                break;
            }

            const parentId = currentFolder.parentId;
            currentFolder = parentId
                ? items.find((item) => item.id === parentId)
                : undefined;
        }

        setLibrary(folderLibrary);
    }

    function createFolder(): void {
        const name = folderName.trim();

        if (!name) {
            return;
        }

        setItems((currentItems) => [
            ...currentItems,
            {
                id: `folder-${Date.now()}`,
                name,
                kind: 'folder',
                parentId: activeFolderId,
                updatedAt: 'Created just now',
                owner: 'You',
                description: 'New company drive folder.',
            },
        ]);
        setFolderName('');
        setIsFolderDialogOpen(false);
    }

    function createDocument(): void {
        const name = documentName.trim();

        if (!name) {
            return;
        }

        setItems((currentItems) => [
            ...currentItems,
            {
                id: `document-${Date.now()}`,
                name: name.endsWith('.docx') ? name : `${name}.docx`,
                kind: 'document',
                parentId: activeFolderId,
                updatedAt: 'Created just now',
                owner: 'You',
                size: '0 KB',
                description:
                    'New draft document. Saving and collaboration will be connected next.',
            },
        ]);
        setDocumentName('');
        setIsDocumentDialogOpen(false);
    }

    function addFiles(selectedFiles: File[], parentId: string | null): void {
        if (selectedFiles.length === 0) {
            return;
        }

        const folderId = companyDriveFolderId(parentId);

        if (folderId === null) {
            setUploadError(
                'Open a client or organisation folder before uploading files.',
            );

            return;
        }

        router.post(
            store().url,
            { folder_id: folderId, documents: selectedFiles },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => {
                    setUploadError(null);
                    setIsUploading(true);
                },
                onError: () =>
                    setUploadError(
                        'The files could not be uploaded. Check the file type and try again.',
                    ),
                onFinish: () => setIsUploading(false),
            },
        );
    }

    function addUploads(event: ChangeEvent<HTMLInputElement>): void {
        addFiles(Array.from(event.target.files ?? []), activeFolderId);
        event.target.value = '';
    }

    function moveItem(itemId: string, targetFolderId: string): void {
        const item = items.find((candidate) => candidate.id === itemId);

        if (
            !item ||
            item.id === targetFolderId ||
            item.parentId === targetFolderId
        ) {
            return;
        }

        let parentId: string | null = targetFolderId;

        while (parentId) {
            if (parentId === itemId) {
                return;
            }

            parentId =
                items.find((candidate) => candidate.id === parentId)
                    ?.parentId ?? null;
        }

        setItems((currentItems) =>
            currentItems.map((candidate) =>
                candidate.id === itemId
                    ? {
                          ...candidate,
                          parentId: targetFolderId,
                          updatedAt: 'Moved just now',
                      }
                    : candidate,
            ),
        );
    }

    function dropOnFolder(
        event: DragEvent<HTMLElement>,
        targetFolderId: string,
    ): void {
        event.preventDefault();
        event.stopPropagation();

        const targetFolder = items.find((item) => item.id === targetFolderId);

        if (!targetFolder || targetFolder.kind !== 'folder') {
            return;
        }

        const itemId = event.dataTransfer.getData(
            'application/x-zolomphi-drive-item',
        );

        if (itemId) {
            moveItem(itemId, targetFolderId);
        } else {
            addFiles(Array.from(event.dataTransfer.files), targetFolderId);
        }

        setDraggedItemId(null);
        setDropTargetId(null);
        setIsFileDropActive(false);
    }

    function dropOnCurrentFolder(event: DragEvent<HTMLElement>): void {
        event.preventDefault();
        const itemId = event.dataTransfer.getData(
            'application/x-zolomphi-drive-item',
        );

        if (itemId && activeFolderId) {
            moveItem(itemId, activeFolderId);
        } else if (!itemId) {
            addFiles(Array.from(event.dataTransfer.files), activeFolderId);
        }

        setDraggedItemId(null);
        setDropTargetId(null);
        setIsFileDropActive(false);
    }

    function chooseFolderUpload(): void {
        const input = folderInputRef.current;

        if (!input) {
            return;
        }

        input.setAttribute('webkitdirectory', '');
        input.setAttribute('directory', '');
        input.click();
    }

    return (
        <>
            <Head title="Files" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Company drive
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Files
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            A shared home for firm documents, templates, and
                            practice resources.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsDocumentDialogOpen(true)}
                        >
                            <FilePlus2 />
                            New file
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsFolderDialogOpen(true)}
                        >
                            <FolderPlus />
                            New folder
                        </Button>
                        <Button
                            type="button"
                            disabled={isUploading}
                            onClick={() => fileInputRef.current?.click()}
                        >
                            <Upload />
                            {isUploading ? 'Uploading…' : 'Upload'}
                        </Button>
                    </div>
                </header>

                <div className="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
                    <aside className="h-fit rounded-xl border border-border/70 bg-background p-3 lg:sticky lg:top-6">
                        <p className="px-3 pt-1 pb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Library
                        </p>
                        <nav
                            className="grid gap-1"
                            aria-label="Company drive library"
                        >
                            <LibraryButton
                                active={library === 'client-files'}
                                icon={FolderOpen}
                                label="Clients"
                                onClick={() => selectLibrary('client-files')}
                            />
                            <LibraryButton
                                active={library === 'organization-files'}
                                icon={Building2}
                                label="Organisations"
                                onClick={() =>
                                    selectLibrary('organization-files')
                                }
                            />
                        </nav>

                        <div className="mt-8 border-t pt-4">
                            <div className="rounded-lg border bg-muted/30 p-4">
                                <div className="flex items-center gap-2 font-semibold">
                                    <HardDrive className="size-4 text-primary" />
                                    Storage
                                </div>
                                <p className="mt-3 text-sm text-muted-foreground">
                                    {formatFileSize(storage.usedBytes)} of{' '}
                                    {formatFileSize(storage.quotaBytes)} used
                                </p>
                                <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-primary/10">
                                    <div
                                        className="h-full rounded-full bg-[#EA702D] transition-[width]"
                                        style={{ width: `${storageUsagePercent}%` }}
                                    />
                                </div>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {storageUsagePercent.toFixed(1)}% of firm storage used
                                </p>
                            </div>
                        </div>
                    </aside>

                    <main
                        className="relative min-w-0"
                        onDragOver={(event) => {
                            if (
                                Array.from(event.dataTransfer.types).includes(
                                    'Files',
                                )
                            ) {
                                event.preventDefault();
                                setIsFileDropActive(true);
                            }
                        }}
                        onDragLeave={(event) => {
                            if (event.currentTarget === event.target) {
                                setIsFileDropActive(false);
                            }
                        }}
                        onDrop={dropOnCurrentFolder}
                    >
                        <h2 className="mb-4 text-xl font-semibold tracking-tight">
                            {libraryTitle[library]}
                        </h2>
                        {uploadError && (
                            <p className="mb-4 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                                {uploadError}
                            </p>
                        )}
                        <div className="overflow-hidden rounded-xl border border-border/70 bg-background">
                            <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="relative w-full sm:max-w-xl">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Search files and folders..."
                                        className="pl-9"
                                    />
                                </div>
                                <div className="flex items-center gap-2 self-end sm:self-auto">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={chooseFolderUpload}
                                    >
                                        <FolderOpen />
                                        Upload folder
                                    </Button>
                                    <div className="flex rounded-md border bg-muted/40 p-1">
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant={
                                                view === 'grid'
                                                    ? 'secondary'
                                                    : 'ghost'
                                            }
                                            onClick={() => setView('grid')}
                                            aria-label="Grid view"
                                        >
                                            <Grid2X2 />
                                        </Button>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant={
                                                view === 'list'
                                                    ? 'secondary'
                                                    : 'ghost'
                                            }
                                            onClick={() => setView('list')}
                                            aria-label="List view"
                                        >
                                            <List />
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-1 border-b px-4 py-3 text-sm">
                                {activeFolderId && (
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        className="mr-1 size-7"
                                        onClick={() =>
                                            setActiveFolderId(
                                                folderTrail.at(-1)?.parentId ??
                                                    null,
                                            )
                                        }
                                        aria-label="Back to parent folder"
                                    >
                                        <ArrowLeft />
                                    </Button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => selectLibrary('workspace')}
                                    className="font-medium text-primary hover:underline"
                                >
                                    Company drive
                                </button>
                                {folderTrail.map((folder) => (
                                    <div
                                        key={folder.id}
                                        className="flex items-center gap-1"
                                    >
                                        <ChevronRight className="size-4 text-muted-foreground" />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                openFolder(folder.id)
                                            }
                                            className="text-muted-foreground hover:text-foreground hover:underline"
                                        >
                                            {folder.name}
                                        </button>
                                    </div>
                                ))}
                            </div>

                            <div className="p-4 md:p-6">
                                {search && (
                                    <p className="mb-5 text-sm text-muted-foreground">
                                        {visibleItems.length} result
                                        {visibleItems.length === 1
                                            ? ''
                                            : 's'}{' '}
                                        for “{search}”
                                    </p>
                                )}

                                {!search && folders.length > 0 && (
                                    <DriveSection
                                        title="Folders"
                                        items={folders}
                                        view={view}
                                        onOpenFolder={openFolder}
                                        onPreview={setPreviewItem}
                                        draggedItemId={draggedItemId}
                                        dropTargetId={dropTargetId}
                                        onDragStart={setDraggedItemId}
                                        onDragEnd={() => {
                                            setDraggedItemId(null);
                                            setDropTargetId(null);
                                        }}
                                        onFolderDragOver={setDropTargetId}
                                        onDropOnFolder={dropOnFolder}
                                    />
                                )}

                                {fileItems.length > 0 && (
                                    <DriveSection
                                        title={search ? 'Results' : 'Files'}
                                        items={fileItems}
                                        view={view}
                                        onOpenFolder={openFolder}
                                        onPreview={setPreviewItem}
                                        draggedItemId={draggedItemId}
                                        dropTargetId={dropTargetId}
                                        onDragStart={setDraggedItemId}
                                        onDragEnd={() => {
                                            setDraggedItemId(null);
                                            setDropTargetId(null);
                                        }}
                                        onFolderDragOver={setDropTargetId}
                                        onDropOnFolder={dropOnFolder}
                                    />
                                )}

                                {visibleItems.length === 0 && (
                                    <div className="flex min-h-72 flex-col items-center justify-center rounded-lg border border-dashed p-6 text-center">
                                        <div className="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <HardDrive className="size-6" />
                                        </div>
                                        <h2 className="mt-4 font-semibold">
                                            {search
                                                ? 'No matching files'
                                                : 'This folder is empty'}
                                        </h2>
                                        <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                                            {search
                                                ? 'Try a different search term or clear the search to browse your drive.'
                                                : 'Create a folder, start a file, or select items to upload here.'}
                                        </p>
                                        {!search && (
                                            <Button
                                                type="button"
                                                className="mt-4"
                                                onClick={() =>
                                                    fileInputRef.current?.click()
                                                }
                                            >
                                                <Upload />
                                                Upload files
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                        {isFileDropActive && (
                            <div className="pointer-events-none absolute inset-0 z-10 grid place-items-center rounded-xl border-2 border-dashed border-primary bg-primary/5 p-6">
                                <div className="rounded-lg bg-background px-4 py-3 text-center shadow-sm">
                                    <Upload className="mx-auto size-5 text-primary" />
                                    <p className="mt-2 text-sm font-medium">
                                        Drop files to add them here
                                    </p>
                                </div>
                            </div>
                        )}
                    </main>
                </div>
            </div>

            <input
                ref={fileInputRef}
                type="file"
                multiple
                className="hidden"
                onChange={addUploads}
            />
            <input
                ref={folderInputRef}
                type="file"
                multiple
                className="hidden"
                onChange={addUploads}
            />

            <Dialog
                open={isFolderDialogOpen}
                onOpenChange={setIsFolderDialogOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create folder</DialogTitle>
                        <DialogDescription>
                            Add a folder to the current location in the company
                            drive.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="folder-name">Folder name</Label>
                        <Input
                            id="folder-name"
                            value={folderName}
                            onChange={(event) =>
                                setFolderName(event.target.value)
                            }
                            placeholder="e.g. Client templates"
                            autoFocus
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    createFolder();
                                }
                            }}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsFolderDialogOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="button" onClick={createFolder}>
                            Create folder
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isDocumentDialogOpen}
                onOpenChange={setIsDocumentDialogOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create file</DialogTitle>
                        <DialogDescription>
                            Start a new Word document in the current
                            company-drive folder.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="document-name">File name</Label>
                        <Input
                            id="document-name"
                            value={documentName}
                            onChange={(event) =>
                                setDocumentName(event.target.value)
                            }
                            placeholder="e.g. Meeting notes"
                            autoFocus
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    createDocument();
                                }
                            }}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsDocumentDialogOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="button" onClick={createDocument}>
                            Create file
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={previewItem !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        setPreviewItem(null);
                    }
                }}
            >
                <DialogContent className="max-w-2xl">
                    {previewItem && (
                        <>
                            <DialogHeader>
                                <div className="mb-2 flex size-11 items-center justify-center rounded-lg bg-muted">
                                    {itemIcon(previewItem.kind, 'size-6')}
                                </div>
                                <DialogTitle className="pr-8">
                                    {previewItem.name}
                                </DialogTitle>
                                <DialogDescription>
                                    {previewItem.description}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="grid min-h-56 place-items-center rounded-lg border bg-muted/30 p-6 text-center">
                                <div>
                                    <Eye className="mx-auto size-7 text-primary" />
                                    <p className="mt-3 font-medium">
                                        File preview
                                    </p>
                                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                                        This file is securely stored in the
                                        company drive.
                                    </p>
                                    {previewItem.downloadUrl && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="mt-4"
                                            onClick={() =>
                                                window.location.assign(
                                                    previewItem.downloadUrl ??
                                                        '',
                                                )
                                            }
                                        >
                                            <Upload className="rotate-180" />
                                            Download file
                                        </Button>
                                    )}
                                </div>
                            </div>
                            <div className="grid gap-3 text-sm sm:grid-cols-3">
                                <PreviewDetail
                                    label="Owner"
                                    value={previewItem.owner}
                                />
                                <PreviewDetail
                                    label="Size"
                                    value={previewItem.size ?? 'Folder'}
                                />
                                <PreviewDetail
                                    label="Updated"
                                    value={previewItem.updatedAt}
                                />
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

type DriveSectionProps = {
    title: string;
    items: DriveItem[];
    view: 'grid' | 'list';
    onOpenFolder: (folderId: string) => void;
    onPreview: (item: DriveItem) => void;
    draggedItemId: string | null;
    dropTargetId: string | null;
    onDragStart: (itemId: string) => void;
    onDragEnd: () => void;
    onFolderDragOver: (folderId: string | null) => void;
    onDropOnFolder: (event: DragEvent<HTMLElement>, folderId: string) => void;
};

type LibraryButtonProps = {
    active: boolean;
    icon: LucideIcon;
    label: string;
    onClick: () => void;
};

function LibraryButton({
    active,
    icon: Icon,
    label,
    onClick,
}: LibraryButtonProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground',
                active && 'bg-primary/10 text-primary hover:bg-primary/10',
            )}
        >
            <Icon className="size-4" />
            {label}
        </button>
    );
}

function DriveSection({
    title,
    items,
    view,
    onOpenFolder,
    onPreview,
    draggedItemId,
    dropTargetId,
    onDragStart,
    onDragEnd,
    onFolderDragOver,
    onDropOnFolder,
}: DriveSectionProps) {
    return (
        <section className="not-last:mb-8">
            <h2 className="mb-3 text-sm font-semibold">{title}</h2>
            <div
                className={cn(
                    view === 'grid'
                        ? 'grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3'
                        : 'divide-y rounded-lg border',
                )}
            >
                {items.map((item) => (
                    <DriveItemCard
                        key={item.id}
                        item={item}
                        view={view}
                        onOpenFolder={onOpenFolder}
                        onPreview={onPreview}
                        draggedItemId={draggedItemId}
                        dropTargetId={dropTargetId}
                        onDragStart={onDragStart}
                        onDragEnd={onDragEnd}
                        onFolderDragOver={onFolderDragOver}
                        onDropOnFolder={onDropOnFolder}
                    />
                ))}
            </div>
        </section>
    );
}

type DriveItemCardProps = {
    item: DriveItem;
    view: 'grid' | 'list';
    onOpenFolder: (folderId: string) => void;
    onPreview: (item: DriveItem) => void;
    draggedItemId: string | null;
    dropTargetId: string | null;
    onDragStart: (itemId: string) => void;
    onDragEnd: () => void;
    onFolderDragOver: (folderId: string | null) => void;
    onDropOnFolder: (event: DragEvent<HTMLElement>, folderId: string) => void;
};

function DriveItemCard({
    item,
    view,
    onOpenFolder,
    onPreview,
    draggedItemId,
    dropTargetId,
    onDragStart,
    onDragEnd,
    onFolderDragOver,
    onDropOnFolder,
}: DriveItemCardProps) {
    const openItem = (): void => {
        if (item.kind === 'folder') {
            onOpenFolder(item.id);

            return;
        }

        onPreview(item);
    };

    const isFolderDropTarget =
        item.kind === 'folder' && item.id !== draggedItemId;
    const isDropTarget = isFolderDropTarget && dropTargetId === item.id;

    function beginDrag(event: DragEvent<HTMLButtonElement>): void {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData(
            'application/x-zolomphi-drive-item',
            item.id,
        );
        onDragStart(item.id);
    }

    function dragOverFolder(event: DragEvent<HTMLButtonElement>): void {
        if (!isFolderDropTarget) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.dataTransfer.dropEffect = 'move';
        onFolderDragOver(item.id);
    }

    function leaveFolder(event: DragEvent<HTMLButtonElement>): void {
        if (event.currentTarget === event.target) {
            onFolderDragOver(null);
        }
    }

    if (view === 'list') {
        return (
            <button
                type="button"
                onClick={openItem}
                draggable
                onDragStart={beginDrag}
                onDragEnd={onDragEnd}
                onDragOver={dragOverFolder}
                onDragLeave={leaveFolder}
                onDrop={(event) => onDropOnFolder(event, item.id)}
                className={cn(
                    'flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/50',
                    isDropTarget &&
                        'bg-primary/10 ring-2 ring-primary ring-inset',
                    draggedItemId === item.id && 'opacity-50',
                )}
            >
                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted/60">
                    {itemIcon(item.kind, 'size-5')}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">{item.name}</p>
                    <p className="truncate text-sm text-muted-foreground">
                        {item.owner} · {item.size ?? 'Folder'}
                    </p>
                </div>
                <p className="hidden text-sm text-muted-foreground md:block">
                    {item.updatedAt}
                </p>
                {item.kind === 'folder' ? (
                    <ChevronRight className="size-4 text-muted-foreground" />
                ) : (
                    <Eye className="size-4 text-muted-foreground" />
                )}
            </button>
        );
    }

    return (
        <button
            type="button"
            onClick={openItem}
            draggable
            onDragStart={beginDrag}
            onDragEnd={onDragEnd}
            onDragOver={dragOverFolder}
            onDragLeave={leaveFolder}
            onDrop={(event) => onDropOnFolder(event, item.id)}
            className={cn(
                'group flex min-h-44 flex-col rounded-xl border border-border/70 p-4 text-left transition-all hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                isDropTarget &&
                    'border-primary bg-primary/5 ring-2 ring-primary',
                draggedItemId === item.id && 'opacity-50',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex size-11 items-center justify-center rounded-lg bg-muted/60">
                    {itemIcon(item.kind)}
                </div>
                {item.kind === 'folder' ? (
                    <ChevronRight className="mt-1 size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                ) : (
                    <Eye className="mt-1 size-4 text-muted-foreground" />
                )}
            </div>
            <div className="mt-auto pt-5">
                <p className="truncate font-medium">{item.name}</p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {item.size ?? 'Folder'} · {item.updatedAt}
                </p>
                <p className="mt-3 truncate text-xs text-muted-foreground">
                    {item.owner}
                </p>
            </div>
        </button>
    );
}

function PreviewDetail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 truncate font-medium">{value}</p>
        </div>
    );
}

FilesIndex.layout = {
    breadcrumbs: [{ title: 'Files', href: files() }],
};
