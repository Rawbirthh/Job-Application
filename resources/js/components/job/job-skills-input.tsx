import { useState } from 'react';
import { Plus, X } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    value: string[];
    onChange: (value: string[]) => void;
    disabled?: boolean;
    error?: string;
    description?: string;
};

/**
 * A free-text skill entry used on the employer job form.
 *
 * The values are kept exactly as they were typed; the server normalises them
 * for matching.
 */
export default function JobSkillsInput({
    id,
    value,
    onChange,
    disabled = false,
    error,
    description,
}: Props) {
    const [input, setInput] = useState('');
    const [inputError, setInputError] = useState<string | null>(null);

    const addSkill = () => {
        const skill = input.trim();

        if (!skill) {
            return;
        }

        if (value.length >= 30) {
            setInputError('You can add up to 30 skills.');
            return;
        }

        const exists = value.some(
            (entry) => entry.toLowerCase() === skill.toLowerCase(),
        );

        if (exists) {
            setInputError(`“${skill}” is already listed.`);
            return;
        }

        onChange([...value, skill]);
        setInput('');
        setInputError(null);
    };

    const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addSkill();
        }
    };

    const removeSkill = (index: number) => {
        onChange(value.filter((_, position) => position !== index));
    };

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>Skills</Label>
            <div className="flex gap-2">
                <Input
                    id={id}
                    value={input}
                    onChange={(event) => {
                        setInput(event.target.value);
                        if (inputError) {
                            setInputError(null);
                        }
                    }}
                    onKeyDown={handleKeyDown}
                    maxLength={50}
                    disabled={disabled}
                    placeholder="Type a skill and press Enter"
                    aria-label="Add skill"
                    aria-invalid={inputError ? true : undefined}
                    aria-describedby={`${id}-input-error`}
                    className="sm:max-w-xs"
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={addSkill}
                    disabled={disabled}
                >
                    <Plus className="size-4" />
                    Add
                </Button>
            </div>
            <InputError
                id={`${id}-input-error`}
                message={inputError ?? undefined}
            />
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
            <InputError id={`${id}-error`} message={error} />
            {value.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No skills listed yet.
                </p>
            ) : (
                <div className="flex flex-wrap gap-2">
                    {value.map((skill, index) => (
                        <Badge
                            key={`${skill}-${index}`}
                            variant="secondary"
                            className="flex items-center gap-1"
                        >
                            {skill}
                            <button
                                type="button"
                                onClick={() => removeSkill(index)}
                                disabled={disabled}
                                className="rounded-sm text-muted-foreground hover:text-foreground"
                                aria-label={`Remove ${skill}`}
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            )}
        </div>
    );
}
