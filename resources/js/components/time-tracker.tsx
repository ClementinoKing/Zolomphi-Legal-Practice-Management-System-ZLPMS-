import { useHttp, usePage } from '@inertiajs/react';
import { Pause, Play, Square, Timer, Trash2 } from 'lucide-react';
import { createContext, useContext, useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Spinner } from '@/components/ui/spinner';
import { useCurrency } from '@/hooks/use-currency';
import {
    complete,
    discard,
    matters,
    pause,
    resume,
    start,
    stop,
} from '@/routes/time-tracker';

type TrackedMatter = {
    id: number;
    matter_number: string;
    title: string;
    client_name: string;
    default_hourly_rate: string | null;
};

type TimeTracker = {
    id: number;
    status: 'running' | 'paused' | 'stopped';
    elapsed_seconds: number;
    started_at: string;
    last_resumed_at: string | null;
    paused_at: string | null;
    stopped_at: string | null;
    entry_date: string;
    matter: TrackedMatter;
};

type TimerRequestData = {
    matter_id?: number;
    entry_date?: string;
    description?: string;
    hourly_rate?: number | null;
    is_billable?: boolean;
};

type TimerResponse = {
    tracker: TimeTracker | null;
    server_now: string;
};

type MatterOptionsResponse = {
    matters: TrackedMatter[];
};

type TimeTrackerContextValue = {
    tracker: TimeTracker | null;
    elapsedSeconds: number;
    dialogOpen: boolean;
    setDialogOpen: (open: boolean) => void;
    startTimer: (matterId: number) => Promise<boolean>;
    pauseTimer: () => Promise<boolean>;
    resumeTimer: () => Promise<boolean>;
    stopTimer: () => Promise<boolean>;
    completeTimer: (values: {
        entryDate: string;
        description: string;
        hourlyRate: number | null;
        isBillable: boolean;
    }) => Promise<boolean>;
    discardTimer: () => Promise<boolean>;
    matters: TrackedMatter[];
    loadMatters: () => Promise<void>;
    processing: boolean;
    errors: Record<string, string>;
    matterOptionsLoading: boolean;
};

const TimeTrackerContext = createContext<TimeTrackerContextValue | null>(null);
const floatingTimerTopStorageKey = 'zolomphi.floating-timer.top';
const floatingTimerViewportOffset = 16;
const floatingTimerBottomSnapDistance = 72;

export function TimeTrackerProvider({
    children,
}: {
    children: React.ReactNode;
}) {
    const sharedTracker =
        (usePage().props.timeTracker as TimeTracker | null | undefined) ?? null;
    const [tracker, setTracker] = useState<TimeTracker | null>(sharedTracker);
    const [syncedAt, setSyncedAt] = useState(0);
    const [clock, setClock] = useState(0);
    const [dialogOpen, setDialogOpen] = useState(false);
    const timerRequest = useHttp<TimerRequestData, TimerResponse>({});
    const matterOptionsRequest = useHttp<
        Record<string, never>,
        MatterOptionsResponse
    >({});

    useEffect(() => {
        if (tracker?.status !== 'running') {
            return;
        }

        const interval = window.setInterval(() => {
            const timestamp = Date.now();

            if (syncedAt === 0) {
                setSyncedAt(timestamp);

                return;
            }

            setClock(timestamp);
        }, 1000);

        return () => window.clearInterval(interval);
    }, [syncedAt, tracker?.status]);

    const elapsedSeconds =
        tracker?.status === 'running' && syncedAt !== 0
            ? tracker.elapsed_seconds +
              Math.max(0, Math.floor((clock - syncedAt) / 1000))
            : (tracker?.elapsed_seconds ?? 0);

    function applyResponse(response: TimerResponse | undefined): boolean {
        if (response === undefined) {
            return false;
        }

        setTracker(response.tracker);
        const timestamp = Date.now();
        setSyncedAt(timestamp);
        setClock(timestamp);

        return true;
    }

    async function startTimer(matterId: number): Promise<boolean> {
        timerRequest.clearErrors();
        timerRequest.setData({ matter_id: matterId });

        return applyResponse(await timerRequest.post(start().url));
    }

    async function pauseTimer(): Promise<boolean> {
        if (tracker === null) {
            return false;
        }

        timerRequest.clearErrors();
        timerRequest.setData({});

        return applyResponse(await timerRequest.patch(pause(tracker.id).url));
    }

    async function resumeTimer(): Promise<boolean> {
        if (tracker === null) {
            return false;
        }

        timerRequest.clearErrors();
        timerRequest.setData({});

        return applyResponse(await timerRequest.patch(resume(tracker.id).url));
    }

    async function stopTimer(): Promise<boolean> {
        if (tracker === null) {
            return false;
        }

        timerRequest.clearErrors();
        timerRequest.setData({});
        const stopped = applyResponse(
            await timerRequest.patch(stop(tracker.id).url),
        );

        if (stopped) {
            setDialogOpen(true);
        }

        return stopped;
    }

    async function completeTimer(values: {
        entryDate: string;
        description: string;
        hourlyRate: number | null;
        isBillable: boolean;
    }): Promise<boolean> {
        if (tracker === null) {
            return false;
        }

        timerRequest.clearErrors();
        timerRequest.setData({
            entry_date: values.entryDate,
            description: values.description,
            hourly_rate: values.hourlyRate,
            is_billable: values.isBillable,
        });
        const completed = applyResponse(
            await timerRequest.post(complete(tracker.id).url),
        );

        if (completed) {
            setDialogOpen(false);
        }

        return completed;
    }

    async function discardTimer(): Promise<boolean> {
        if (tracker === null) {
            return false;
        }

        timerRequest.clearErrors();
        timerRequest.setData({});
        const discarded = applyResponse(
            await timerRequest.delete(discard(tracker.id).url),
        );

        if (discarded) {
            setDialogOpen(false);
        }

        return discarded;
    }

    async function loadMatters(): Promise<void> {
        if (
            matterOptionsRequest.processing ||
            matterOptionsRequest.response !== null
        ) {
            return;
        }

        await matterOptionsRequest.get(matters().url);
    }

    const context: TimeTrackerContextValue = {
        tracker,
        elapsedSeconds,
        dialogOpen,
        setDialogOpen,
        startTimer,
        pauseTimer,
        resumeTimer,
        stopTimer,
        completeTimer,
        discardTimer,
        matters: matterOptionsRequest.response?.matters ?? [],
        loadMatters,
        processing: timerRequest.processing,
        errors: timerRequest.errors,
        matterOptionsLoading: matterOptionsRequest.processing,
    };

    return (
        <TimeTrackerContext.Provider value={context}>
            {children}
            <TimeTrackerDialog />
            <FloatingTimeTracker />
        </TimeTrackerContext.Provider>
    );
}

export function useTimeTracker(): TimeTrackerContextValue {
    const context = useContext(TimeTrackerContext);

    if (context === null) {
        throw new Error(
            'useTimeTracker must be used within TimeTrackerProvider.',
        );
    }

    return context;
}

function TimeTrackerDialog() {
    const { tracker, dialogOpen, setDialogOpen } = useTimeTracker();

    return (
        <Dialog modal={false} open={dialogOpen} onOpenChange={setDialogOpen}>
            <DialogContent className="max-h-[calc(100vh-2rem)] w-[calc(100vw-2rem)] overflow-x-hidden overflow-y-auto sm:max-w-xl">
                {tracker === null ? (
                    <StartTimerForm />
                ) : tracker.status === 'stopped' ? (
                    <CompleteTimerForm />
                ) : (
                    <ActiveTimerSummary />
                )}
            </DialogContent>
        </Dialog>
    );
}

function StartTimerForm() {
    const {
        matters: availableMatters,
        loadMatters,
        matterOptionsLoading,
        startTimer,
        processing,
        errors,
        setDialogOpen,
    } = useTimeTracker();
    const { formatMoney } = useCurrency();
    const [matterId, setMatterId] = useState('');
    const selectedMatter = availableMatters.find(
        (matter) => matter.id === Number(matterId),
    );

    useEffect(() => {
        void loadMatters();
    }, [loadMatters]);

    return (
        <form
            className="grid min-w-0 gap-5"
            onSubmit={async (event) => {
                event.preventDefault();

                if (await startTimer(Number(matterId))) {
                    setDialogOpen(false);
                }
            }}
        >
            <DialogHeader>
                <DialogTitle>Start matter timer</DialogTitle>
                <DialogDescription>
                    Select the matter you are working on. Your timer will stay
                    available while you move around the practice workspace.
                </DialogDescription>
            </DialogHeader>
            <div className="grid min-w-0 gap-2">
                <Label htmlFor="timer-matter">Matter</Label>
                <SearchableSelect
                    id="timer-matter"
                    value={matterId}
                    required
                    disabled={matterOptionsLoading}
                    placeholder={
                        matterOptionsLoading
                            ? 'Loading matters…'
                            : 'Select a matter'
                    }
                    options={availableMatters.map((matter) => ({
                        value: String(matter.id),
                        label: `${matter.matter_number} · ${matter.title}`,
                        searchText: matter.client_name,
                    }))}
                    onChange={setMatterId}
                />
                <InputError message={errors.matter_id ?? errors.timer} />
            </div>
            {selectedMatter && (
                <div className="rounded-xl border border-border/70 bg-muted/30 p-4">
                    <p className="font-medium">{selectedMatter.title}</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {selectedMatter.client_name} ·{' '}
                        {selectedMatter.matter_number}
                    </p>
                    <p className="mt-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Matter default rate:{' '}
                        {selectedMatter.default_hourly_rate === null
                            ? 'Not set'
                            : formatMoney(
                                  Number(selectedMatter.default_hourly_rate),
                              )}
                    </p>
                </div>
            )}
            <DialogFooter className="w-full">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setDialogOpen(false)}
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    disabled={
                        processing || matterOptionsLoading || matterId === ''
                    }
                >
                    {processing && <Spinner />}
                    <Timer />
                    Start timer
                </Button>
            </DialogFooter>
        </form>
    );
}

function ActiveTimerSummary() {
    const {
        tracker,
        elapsedSeconds,
        pauseTimer,
        resumeTimer,
        stopTimer,
        processing,
    } = useTimeTracker();

    if (tracker === null) {
        return null;
    }

    const isRunning = tracker.status === 'running';

    return (
        <div className="grid gap-5">
            <DialogHeader>
                <DialogTitle>Timer in progress</DialogTitle>
                <DialogDescription>
                    {tracker.matter.client_name} ·{' '}
                    {tracker.matter.matter_number}
                </DialogDescription>
            </DialogHeader>
            <div className="rounded-xl border border-border/70 bg-muted/30 p-5 text-center">
                <p className="font-mono text-4xl font-semibold tracking-tight">
                    {formatElapsed(elapsedSeconds)}
                </p>
                <p className="mt-2 font-medium">{tracker.matter.title}</p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {isRunning ? 'Timer is running' : 'Timer is paused'}
                </p>
            </div>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    disabled={processing}
                    onClick={() => (isRunning ? pauseTimer() : resumeTimer())}
                >
                    {isRunning ? <Pause /> : <Play />}
                    {isRunning ? 'Pause' : 'Resume'}
                </Button>
                <Button
                    type="button"
                    disabled={processing}
                    onClick={() => void stopTimer()}
                >
                    {processing && <Spinner />}
                    <Square />
                    Stop and save
                </Button>
            </DialogFooter>
        </div>
    );
}

function CompleteTimerForm() {
    const {
        tracker,
        elapsedSeconds,
        completeTimer,
        discardTimer,
        setDialogOpen,
        processing,
        errors,
    } = useTimeTracker();
    const [entryDate, setEntryDate] = useState(tracker?.entry_date ?? '');
    const [description, setDescription] = useState('');
    const [hourlyRate, setHourlyRate] = useState(
        tracker?.matter.default_hourly_rate ?? '',
    );
    const [isBillable, setIsBillable] = useState(true);

    if (tracker === null) {
        return null;
    }

    return (
        <form
            className="grid gap-5"
            onSubmit={async (event) => {
                event.preventDefault();
                await completeTimer({
                    entryDate,
                    description,
                    hourlyRate: hourlyRate === '' ? null : Number(hourlyRate),
                    isBillable,
                });
            }}
        >
            <DialogHeader>
                <DialogTitle>Save timed work</DialogTitle>
                <DialogDescription>
                    {tracker.matter.client_name} ·{' '}
                    {tracker.matter.matter_number}
                </DialogDescription>
            </DialogHeader>
            <div className="rounded-xl border border-primary/20 bg-primary/5 p-4">
                <p className="font-mono text-3xl font-semibold">
                    {formatElapsed(elapsedSeconds)}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    Saved as {formatQuarterHours(elapsedSeconds)} hours, rounded
                    up to the nearest quarter hour.
                </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="timer-entry-date">Date</Label>
                    <Input
                        id="timer-entry-date"
                        type="date"
                        value={entryDate}
                        onChange={(event) => setEntryDate(event.target.value)}
                        required
                    />
                    <InputError message={errors.entry_date} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="timer-hourly-rate">Hourly rate</Label>
                    <Input
                        id="timer-hourly-rate"
                        type="number"
                        min="0"
                        step="0.01"
                        value={hourlyRate}
                        onChange={(event) => setHourlyRate(event.target.value)}
                    />
                    <InputError message={errors.hourly_rate} />
                </div>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="timer-description">Activities completed</Label>
                <textarea
                    id="timer-description"
                    value={description}
                    onChange={(event) => setDescription(event.target.value)}
                    required
                    placeholder="Describe the legal work completed during this timed session."
                    className="min-h-28 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                />
                <InputError message={errors.description ?? errors.timer} />
            </div>
            <label className="flex items-center gap-3 rounded-lg border border-border/70 px-3 py-3 text-sm">
                <Checkbox
                    checked={isBillable}
                    onCheckedChange={(checked) =>
                        setIsBillable(checked === true)
                    }
                />
                Billable time
            </label>
            <p className="text-xs text-muted-foreground">
                This creates a draft time entry. You can edit or submit it later
                from the matter workspace.
            </p>
            <DialogFooter className="sm:justify-between">
                <Button
                    type="button"
                    variant="ghost"
                    className="text-destructive hover:text-destructive"
                    disabled={processing}
                    onClick={() => void discardTimer()}
                >
                    <Trash2 />
                    Discard timer
                </Button>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setDialogOpen(false)}
                    >
                        Save later
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Save time entry
                    </Button>
                </div>
            </DialogFooter>
        </form>
    );
}

function FloatingTimeTracker() {
    const {
        tracker,
        elapsedSeconds,
        pauseTimer,
        resumeTimer,
        stopTimer,
        processing,
    } = useTimeTracker();
    const floatingTimerRef = useRef<HTMLElement>(null);
    const dragStateRef = useRef<{
        pointerId: number;
        pointerOffsetY: number;
    } | null>(null);
    const draggedTopRef = useRef<number | null>(null);
    const [floatingTop, setFloatingTop] = useState<number | null>(() => {
        if (typeof window === 'undefined') {
            return null;
        }

        const storedTop = Number.parseFloat(
            window.localStorage.getItem(floatingTimerTopStorageKey) ?? '',
        );

        return Number.isFinite(storedTop) ? storedTop : null;
    });
    const [isDragging, setIsDragging] = useState(false);

    if (tracker === null || tracker.status === 'stopped') {
        return null;
    }

    const isRunning = tracker.status === 'running';

    function getTopBounds(): { min: number; max: number } {
        const cardHeight = floatingTimerRef.current?.offsetHeight ?? 0;

        return {
            min: floatingTimerViewportOffset,
            max: Math.max(
                floatingTimerViewportOffset,
                window.innerHeight - cardHeight - floatingTimerViewportOffset,
            ),
        };
    }

    function clampTop(top: number): number {
        const bounds = getTopBounds();

        return Math.min(Math.max(top, bounds.min), bounds.max);
    }

    function finishDrag(): void {
        const nextTop = draggedTopRef.current;

        if (nextTop === null) {
            return;
        }

        const { max } = getTopBounds();

        if (nextTop >= max - floatingTimerBottomSnapDistance) {
            setFloatingTop(null);
            window.localStorage.removeItem(floatingTimerTopStorageKey);
        } else {
            setFloatingTop(nextTop);
            window.localStorage.setItem(
                floatingTimerTopStorageKey,
                String(nextTop),
            );
        }

        draggedTopRef.current = null;
        dragStateRef.current = null;
        setIsDragging(false);
    }

    return (
        <aside
            ref={floatingTimerRef}
            style={
                floatingTop === null
                    ? undefined
                    : { top: `${floatingTop}px`, bottom: 'auto' }
            }
            className="fixed right-4 bottom-4 z-50 w-[calc(100vw-2rem)] max-w-xs rounded-xl border border-border/70 bg-background p-3 shadow-xl"
        >
            <div
                title="Drag to reposition the timer vertically"
                className={`flex min-w-0 touch-none items-start gap-2.5 select-none ${
                    isDragging
                        ? 'cursor-grabbing'
                        : 'cursor-grab active:cursor-grabbing'
                }`}
                onPointerDown={(event) => {
                    if (
                        event.button !== 0 ||
                        floatingTimerRef.current === null
                    ) {
                        return;
                    }

                    const cardBounds =
                        floatingTimerRef.current.getBoundingClientRect();

                    dragStateRef.current = {
                        pointerId: event.pointerId,
                        pointerOffsetY: event.clientY - cardBounds.top,
                    };
                    draggedTopRef.current = cardBounds.top;
                    event.currentTarget.setPointerCapture(event.pointerId);
                    setIsDragging(true);
                }}
                onPointerMove={(event) => {
                    const dragState = dragStateRef.current;

                    if (dragState?.pointerId !== event.pointerId) {
                        return;
                    }

                    const nextTop = clampTop(
                        event.clientY - dragState.pointerOffsetY,
                    );

                    draggedTopRef.current = nextTop;
                    setFloatingTop(nextTop);
                }}
                onPointerUp={(event) => {
                    if (dragStateRef.current?.pointerId !== event.pointerId) {
                        return;
                    }

                    finishDrag();
                }}
                onPointerCancel={finishDrag}
            >
                <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-primary text-primary-foreground">
                    <Timer className="size-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-xs font-semibold">
                        {tracker.matter.title}
                    </p>
                    <p className="mt-0.5 truncate text-[11px] text-muted-foreground">
                        {tracker.matter.client_name} ·{' '}
                        {tracker.matter.matter_number}
                    </p>
                </div>
                <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium capitalize">
                    {tracker.status}
                </span>
            </div>
            <p className="mt-3 font-mono text-3xl font-semibold tracking-tight">
                {formatElapsed(elapsedSeconds)}
            </p>
            <div className="mt-3 grid grid-cols-2 gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="h-9 text-xs"
                    disabled={processing}
                    onClick={() =>
                        void (isRunning ? pauseTimer() : resumeTimer())
                    }
                >
                    {isRunning ? (
                        <Pause className="size-4" />
                    ) : (
                        <Play className="size-4" />
                    )}
                    {isRunning ? 'Pause' : 'Resume'}
                </Button>
                <Button
                    type="button"
                    className="h-9 text-xs"
                    disabled={processing}
                    onClick={() => void stopTimer()}
                >
                    {processing && <Spinner />}
                    <Square className="size-4" />
                    Stop
                </Button>
            </div>
        </aside>
    );
}

function formatElapsed(totalSeconds: number): string {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return [hours, minutes, seconds]
        .map((value) => String(value).padStart(2, '0'))
        .join(':');
}

function formatQuarterHours(seconds: number): string {
    return (Math.ceil(Math.max(1, seconds) / 900) * 0.25).toFixed(2);
}
