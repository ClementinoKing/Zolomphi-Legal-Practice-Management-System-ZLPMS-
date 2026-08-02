type TrendPoint = { label: string; first: number; second: number };
type DistributionPoint = { label: string; value: number };

const palette = [
    '#191E5E',
    '#EA702D',
    '#5B7CFA',
    '#18A66A',
    '#D9A441',
    '#8B5CF6',
];

export function ComparisonChart({
    title,
    firstLabel,
    secondLabel,
    points,
}: {
    title: string;
    firstLabel: string;
    secondLabel: string;
    points: TrendPoint[];
}) {
    const maximum = Math.max(
        1,
        ...points.flatMap((point) => [point.first, point.second]),
    );

    return (
        <section className="rounded-xl border bg-background p-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="font-semibold">{title}</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Live monthly movement across the selected records.
                    </p>
                </div>
                <div className="flex gap-3 text-xs">
                    <Legend color="bg-primary" label={firstLabel} />
                    <Legend color="bg-orange-500" label={secondLabel} />
                </div>
            </div>
            <div className="mt-7 flex h-52 items-end gap-2 sm:gap-4">
                {points.map((point) => (
                    <div
                        key={point.label}
                        className="flex h-full min-w-0 flex-1 flex-col justify-end gap-2"
                    >
                        <div className="flex flex-1 items-end justify-center gap-1">
                            <span
                                title={`${firstLabel}: ${point.first}`}
                                className="w-1/2 max-w-5 rounded-t bg-primary transition-all"
                                style={{
                                    height: `${Math.max(point.first > 0 ? 6 : 0, (point.first / maximum) * 100)}%`,
                                }}
                            />
                            <span
                                title={`${secondLabel}: ${point.second}`}
                                className="w-1/2 max-w-5 rounded-t bg-orange-500 transition-all"
                                style={{
                                    height: `${Math.max(point.second > 0 ? 6 : 0, (point.second / maximum) * 100)}%`,
                                }}
                            />
                        </div>
                        <span className="truncate text-center text-xs text-muted-foreground">
                            {point.label}
                        </span>
                    </div>
                ))}
            </div>
        </section>
    );
}

export function DistributionChart({
    title,
    entries,
}: {
    title: string;
    entries: DistributionPoint[];
}) {
    const total = entries.reduce((sum, entry) => sum + entry.value, 0);
    let current = 0;
    const gradient =
        entries.length === 0
            ? '#e5e7eb 0deg 360deg'
            : entries
                  .map((entry, index) => {
                      const start = current;

                      current += (entry.value / Math.max(total, 1)) * 360;

                      return `${palette[index % palette.length]} ${start}deg ${current}deg`;
                  })
                  .join(', ');

    return (
        <section className="rounded-xl border bg-background p-5">
            <h2 className="font-semibold">{title}</h2>
            <p className="mt-1 text-sm text-muted-foreground">
                Current distribution of active and closed work.
            </p>
            <div className="mt-6 flex flex-col items-center gap-6 sm:flex-row">
                <div
                    className="relative grid size-40 shrink-0 place-items-center rounded-full"
                    style={{ background: `conic-gradient(${gradient})` }}
                >
                    <div className="grid size-24 place-items-center rounded-full bg-background text-center">
                        <span className="text-2xl font-semibold">{total}</span>
                        <span className="text-xs text-muted-foreground">
                            matters
                        </span>
                    </div>
                </div>
                <div className="grid w-full gap-2">
                    {entries.map((entry, index) => (
                        <div
                            key={entry.label}
                            className="flex items-center justify-between gap-3 text-sm"
                        >
                            <span className="flex items-center gap-2 capitalize">
                                <span
                                    className="size-2.5 rounded-full"
                                    style={{
                                        backgroundColor:
                                            palette[index % palette.length],
                                    }}
                                />
                                {entry.label.replaceAll('_', ' ')}
                            </span>
                            <span className="font-medium">{entry.value}</span>
                        </div>
                    ))}
                    {entries.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No matters recorded.
                        </p>
                    )}
                </div>
            </div>
        </section>
    );
}
function Legend({ color, label }: { color: string; label: string }) {
    return (
        <span className="flex items-center gap-1.5 text-muted-foreground">
            <span className={`size-2.5 rounded-sm ${color}`} />
            {label}
        </span>
    );
}
