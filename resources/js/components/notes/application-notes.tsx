import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import EmployerNoteController from '@/actions/App/Http/Controllers/Employer/EmployerNoteController';
import InputError from '@/components/input-error';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { StickyNote, ChevronDown } from 'lucide-react';
import type { ApplicationNoteItem } from '@/types';

type Props = {
    applicationId: number;
    notes: ApplicationNoteItem[];
    canManage: boolean;
};

function formatNoteDate(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function authorName(author: ApplicationNoteItem['author']): string {
    return author?.name ?? 'Former team member';
}

export function ApplicationNotes({ applicationId, notes, canManage }: Props) {
    const createForm = useForm({ body: '' });
    const editForm = useForm({ body: '' });
    const [editingId, setEditingId] = useState<number | null>(null);

    const submitNote = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        createForm.post(
            EmployerNoteController.store.url({
                application: applicationId,
            }),
            {
                preserveScroll: true,
                onSuccess: () => createForm.reset('body'),
            },
        );
    };

    const startEdit = (note: ApplicationNoteItem) => {
        setEditingId(note.id);
        editForm.setData('body', note.body);
        editForm.clearErrors();
    };

    const cancelEdit = () => {
        setEditingId(null);
        editForm.reset('body');
        editForm.clearErrors();
    };

    const saveEdit = (note: ApplicationNoteItem) => {
        editForm.put(
            EmployerNoteController.update.url({
                application: applicationId,
                note: note.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => setEditingId(null),
            },
        );
    };

    const deleteNote = (note: ApplicationNoteItem) => {
        if (!confirm('Delete this note? It will be removed from the list.')) {
            return;
        }

        setEditingId(null);
        router.delete(
            EmployerNoteController.destroy.url({
                application: applicationId,
                note: note.id,
            }),
            { preserveScroll: true },
        );
    };

    const latest = notes.length > 0 ? notes[0] : null;
    const history = notes.slice(1);

    const renderNote = (note: ApplicationNoteItem) => (
        <li
            key={note.id}
            className="space-y-1.5 rounded-lg border border-border bg-muted/30 p-3"
        >
            <div className="flex items-center justify-between gap-2">
                <div className="text-xs font-medium text-foreground">
                    {authorName(note.author)}
                    <span className="ml-2 font-normal text-muted-foreground">
                        {formatNoteDate(note.created_at)}
                        {note.edited_at ? ' · edited' : ''}
                    </span>
                </div>
                {(note.can_edit || note.can_delete) && (
                    <div className="flex shrink-0 items-center gap-1">
                        {note.can_edit && editingId !== note.id && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 px-2 text-xs"
                                onClick={() => startEdit(note)}
                            >
                                Edit
                            </Button>
                        )}
                        {note.can_delete && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 px-2 text-xs text-destructive hover:text-destructive"
                                onClick={() => deleteNote(note)}
                            >
                                Delete
                            </Button>
                        )}
                    </div>
                )}
            </div>

            {editingId === note.id ? (
                <div className="space-y-2">
                    <Label htmlFor={`edit-note-${note.id}`} className="sr-only">
                        Edit note
                    </Label>
                    <Textarea
                        id={`edit-note-${note.id}`}
                        value={editForm.data.body}
                        onChange={(event) =>
                            editForm.setData('body', event.target.value)
                        }
                        maxLength={5000}
                        rows={3}
                    />
                    <InputError message={editForm.errors.body} />
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            disabled={editForm.processing}
                            onClick={() => saveEdit(note)}
                        >
                            {editForm.processing ? 'Saving...' : 'Save'}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={cancelEdit}
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            ) : (
                <p className="text-sm leading-relaxed whitespace-pre-wrap text-foreground/90">
                    {note.body}
                </p>
            )}
        </li>
    );

    return (
        <Card className="border-border">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-lg">
                    <StickyNote className="size-5 text-teal-600 dark:text-teal-400" />
                    Private Notes
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <p className="text-xs text-muted-foreground">
                    Visible only to your company team. Never shown to the
                    candidate.
                </p>

                {latest === null ? (
                    <p className="text-sm text-muted-foreground italic">
                        No notes yet. Notes are private to your company team.
                    </p>
                ) : (
                    <>
                        <div className="space-y-1">
                            <span className="text-xs font-semibold text-foreground">
                                Latest note
                            </span>
                            <ul>{renderNote(latest)}</ul>
                        </div>

                        {history.length > 0 && (
                            <Collapsible>
                                <CollapsibleTrigger className="flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">
                                    Earlier notes ({history.length})
                                    <ChevronDown className="size-3.5" />
                                </CollapsibleTrigger>
                                <CollapsibleContent className="pt-2">
                                    <ul className="space-y-3">
                                        {history.map((note) =>
                                            renderNote(note),
                                        )}
                                    </ul>
                                </CollapsibleContent>
                            </Collapsible>
                        )}
                    </>
                )}

                {canManage && (
                    <form onSubmit={submitNote} className="space-y-2">
                        <Separator />
                        <Label htmlFor="new-note">Add a note</Label>
                        <Textarea
                            id="new-note"
                            value={createForm.data.body}
                            onChange={(event) =>
                                createForm.setData('body', event.target.value)
                            }
                            placeholder="e.g. Strong REST API background; verify queue knowledge in round 2."
                            maxLength={5000}
                            rows={3}
                        />
                        <InputError message={createForm.errors.body} />
                        <Button type="submit" disabled={createForm.processing}>
                            {createForm.processing
                                ? 'Adding...'
                                : 'Add Private Note'}
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}
