import AppLayout from '@/layouts/app-layout'
import { Head } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import { useEffect, useRef, useState } from 'react'
import { Loader2, Send, CheckCircle2, AlertCircle, Edit3, Plus, Trash2 } from 'lucide-react'

type Message = {
    role: 'user' | 'ai'
    content: string
    toolResults?: any[]
}

type GeminiHistoryItem = {
    role: 'user' | 'model' | 'function'
    parts: any[]
}

export default function OrderChat() {
    const [message, setMessage] = useState('')
    const [messages, setMessages] = useState<Message[]>([])
    const [history, setHistory] = useState<GeminiHistoryItem[]>([])
    const [loading, setLoading] = useState(false)
    const bottomRef = useRef<HTMLDivElement>(null)

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
    }, [messages])

    const sendMessage = async () => {
        if (!message.trim() || loading) return

        const userMessage = message.trim()

        // Append user message immediately
        setMessages(prev => [
            ...prev,
            { role: 'user', content: userMessage },
        ])

        setMessage('')
        setLoading(true)

        try {
            const response = await fetch('/chats/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (
                        document.querySelector(
                            'meta[name="csrf-token"]'
                        ) as HTMLMetaElement
                    ).content,
                },
                body: JSON.stringify({
                    message: userMessage,
                    history: history,
                }),
            })

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}))
                throw new Error(errorData.message || 'Request failed')
            }

            const data = await response.json()

            // Update conversation history
            if (data.history) {
                setHistory(data.history)
            }

            // Add AI response
            setMessages(prev => [
                ...prev,
                {
                    role: 'ai',
                    content: data.reply || 'No response received.',
                    toolResults: data.toolResults,
                },
            ])
        } catch (error) {
            console.error('Chat error:', error)
            setMessages(prev => [
                ...prev,
                {
                    role: 'ai',
                    content: `Error: ${error instanceof Error ? error.message : 'Failed to get response. Please try again.'}`,
                },
            ])
        } finally {
            setLoading(false)
        }
    }

    const renderToolResults = (toolResults?: any[]) => {
        if (!toolResults || toolResults.length === 0) return null

        return (
            <div className="mt-3 space-y-2">
                {toolResults.map((result, idx) => {
                    const response = result.functionResponse?.response
                    const hasError = response?.error
                    const toolName = result.functionResponse?.name
                    const isUpdate = toolName === 'update_order'
                    const isCreate = toolName === 'create_order'
                    const isDelete = toolName === 'delete_order'

                    return (
                        <div
                            key={idx}
                            className={`rounded-md border p-3 text-xs ${hasError
                                    ? 'border-destructive/50 bg-destructive/5'
                                    : isDelete
                                        ? 'border-red-500/50 bg-red-500/5'
                                        : 'border-green-500/50 bg-green-500/5'
                                }`}
                        >
                            <div className="flex items-center gap-2 mb-2">
                                {hasError ? (
                                    <AlertCircle className="h-4 w-4 text-destructive" />
                                ) : isDelete ? (
                                    <Trash2 className="h-4 w-4 text-red-600" />
                                ) : (
                                    <CheckCircle2 className="h-4 w-4 text-green-600" />
                                )}
                                <Badge variant="outline" className="text-xs flex items-center gap-1">
                                    {isCreate && <Plus className="h-3 w-3" />}
                                    {isUpdate && <Edit3 className="h-3 w-3" />}
                                    {isDelete && <Trash2 className="h-3 w-3" />}
                                    {isCreate ? 'Create Order' : isUpdate ? 'Update Order' : isDelete ? 'Delete Order' : toolName || 'Tool'}
                                </Badge>
                            </div>

                            {hasError ? (
                                <p className="text-destructive">{response.error}</p>
                            ) : (
                                <div className="space-y-2 text-muted-foreground">
                                    {response?.message && (
                                        <p className={`font-medium ${isDelete ? 'text-red-600' : 'text-foreground'}`}>
                                            {response.message}
                                        </p>
                                    )}

                                    {/* Show warning for deletions */}
                                    {isDelete && response?.warning && (
                                        <div className="flex items-start gap-2 p-2 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900 rounded">
                                            <AlertCircle className="h-4 w-4 text-red-600 mt-0.5 flex-shrink-0" />
                                            <p className="text-xs text-red-700 dark:text-red-400 font-medium">
                                                {response.warning}
                                            </p>
                                        </div>
                                    )}

                                    {response?.order && (
                                        <div className={`mt-2 rounded border p-2 space-y-1 ${isDelete
                                                ? 'border-red-200 dark:border-red-900 bg-red-50/50 dark:bg-red-950/10'
                                                : 'border-border/50 bg-background/50'
                                            }`}>
                                            <div className="flex items-center justify-between">
                                                <span className="font-mono font-semibold text-foreground">
                                                    #{response.order.order_no}
                                                </span>
                                                {response.order.status && !isDelete && (
                                                    <Badge
                                                        variant={
                                                            response.order.status === 'Delivered' ? 'default' :
                                                                response.order.status === 'Cancelled' ? 'destructive' :
                                                                    'secondary'
                                                        }
                                                        className="text-xs"
                                                    >
                                                        {response.order.status}
                                                    </Badge>
                                                )}
                                                {isDelete && (
                                                    <Badge variant="destructive" className="text-xs">
                                                        DELETED
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                                <div>
                                                    <span className="text-muted-foreground">Client:</span>{' '}
                                                    <span className={`font-medium ${isDelete ? 'line-through text-muted-foreground' : 'text-foreground'}`}>
                                                        {response.order.client_name}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-muted-foreground">Amount:</span>{' '}
                                                    <span className={`font-medium ${isDelete ? 'line-through text-muted-foreground' : 'text-foreground'}`}>
                                                        {response.order.amount?.toLocaleString()} KES
                                                    </span>
                                                </div>
                                                <div className="col-span-2">
                                                    <span className="text-muted-foreground">Product:</span>{' '}
                                                    <span className={`font-medium ${isDelete ? 'line-through text-muted-foreground' : 'text-foreground'}`}>
                                                        {response.order.product_name} (×{response.order.quantity})
                                                    </span>
                                                </div>
                                                {response.order.city && (
                                                    <div>
                                                        <span className="text-muted-foreground">City:</span>{' '}
                                                        <span className={isDelete ? 'line-through text-muted-foreground' : 'text-foreground'}>
                                                            {response.order.city}
                                                        </span>
                                                    </div>
                                                )}
                                                {response.order.merchant && (
                                                    <div>
                                                        <span className="text-muted-foreground">Merchant:</span>{' '}
                                                        <span className={isDelete ? 'line-through text-muted-foreground' : 'text-foreground'}>
                                                            {response.order.merchant}
                                                        </span>
                                                    </div>
                                                )}
                                                {response.order.delivery_date && !isDelete && (
                                                    <div className="col-span-2">
                                                        <span className="text-muted-foreground">Delivery:</span>{' '}
                                                        <span className="text-foreground">
                                                            {new Date(response.order.delivery_date).toLocaleDateString()}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Show changes for updates */}
                                    {isUpdate && response?.changes && response.changes.length > 0 && (
                                        <div className="mt-3 rounded border border-amber-500/30 bg-amber-500/5 p-2">
                                            <p className="font-medium text-foreground mb-2 text-xs flex items-center gap-1">
                                                <Edit3 className="h-3 w-3" />
                                                Changes Made ({response.changes_count}):
                                            </p>
                                            <div className="space-y-1.5">
                                                {response.changes.map((change: any, i: number) => (
                                                    <div
                                                        key={i}
                                                        className="flex items-center gap-2 text-xs bg-background/50 rounded p-1.5"
                                                    >
                                                        <span className="font-medium text-foreground capitalize min-w-[100px]">
                                                            {change.field.replace(/_/g, ' ')}:
                                                        </span>
                                                        <div className="flex items-center gap-1.5 flex-1">
                                                            <span className="line-through text-muted-foreground/70 text-xs">
                                                                {change.old_value || '(empty)'}
                                                            </span>
                                                            <span className="text-muted-foreground">→</span>
                                                            <span className="text-green-600 font-medium">
                                                                {change.new_value || '(empty)'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )
                })}
            </div>
        )
    }

    const quickActions = [
        { label: 'Create Order', prompt: 'Create a new order for' },
        { label: 'Update Status', prompt: 'Update order status to' },
        { label: 'Change Delivery', prompt: 'Change delivery date for order' },
        { label: 'Delete Order', prompt: 'Delete order' },
    ]

    const handleQuickAction = (prompt: string) => {
        setMessage(prompt + ' ')
    }

    return (
        <AppLayout>
            <Head title="AI Order Chat" />

            <div className="flex h-full flex-col rounded-xl border border-sidebar-border bg-background p-4">
                <div className="mb-4 border-b pb-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-semibold">AI Order Assistant</h2>
                            <p className="text-sm text-muted-foreground">
                                Create and update orders using natural language
                            </p>
                        </div>
                        <Badge variant="outline" className="text-xs">
                            Powered by Gemini
                        </Badge>
                    </div>
                </div>

                <ScrollArea className="flex-1 pr-4">
                    <div className="space-y-4 pb-4">
                        {messages.length === 0 && (
                            <div className="flex h-[400px] items-center justify-center">
                                <div className="text-center space-y-4 max-w-2xl">
                                    <div className="text-5xl">🤖</div>
                                    <h3 className="text-lg font-medium">Start a conversation</h3>
                                    <p className="text-sm text-muted-foreground">
                                        I can help you create and update orders using natural language.
                                    </p>

                                    <div className="grid grid-cols-2 gap-2 mt-4">
                                        {quickActions.map((action, idx) => (
                                            <Button
                                                key={idx}
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleQuickAction(action.prompt)}
                                                className="text-xs"
                                            >
                                                {action.label}
                                            </Button>
                                        ))}
                                    </div>

                                    <div className="mt-6 text-left space-y-2 border-t pt-4">
                                        <p className="text-xs font-medium text-muted-foreground">Examples:</p>
                                        <div className="space-y-1 text-xs text-muted-foreground">
                                            <p>• "Create order for John, 2 iPhones, 240k, merchant Adla"</p>
                                            <p>• "Update order JUMANJI-042 status to delivered"</p>
                                            <p>• "Change delivery date for APPLEHUB-043 to Feb 15"</p>
                                            <p className="text-red-600 dark:text-red-400">• "Delete order JUMANJI-042" (requires password)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {messages.map((msg, i) => (
                            <div
                                key={i}
                                className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
                            >
                                <div
                                    className={`max-w-[80%] rounded-lg p-3 text-sm ${msg.role === 'user'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted'
                                        }`}
                                >
                                    <div className="whitespace-pre-wrap break-words">
                                        {msg.content}
                                    </div>
                                    {msg.role === 'ai' && renderToolResults(msg.toolResults)}
                                </div>
                            </div>
                        ))}

                        {loading && (
                            <div className="flex justify-start">
                                <div className="max-w-[80%] rounded-lg bg-muted p-3 text-sm">
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                        <span>AI is thinking...</span>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div ref={bottomRef} />
                    </div>
                </ScrollArea>

                <div className="mt-4 space-y-2">
                    <div className="flex gap-2">
                        <Input
                            placeholder="Type your message... (e.g., 'Create order for...' or 'Update order X to...')"
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault()
                                    sendMessage()
                                }
                            }}
                            disabled={loading}
                            className="flex-1"
                        />
                        <Button
                            onClick={sendMessage}
                            disabled={loading || !message.trim()}
                            size="icon"
                        >
                            {loading ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Send className="h-4 w-4" />
                            )}
                        </Button>
                    </div>
                    <p className="text-xs text-muted-foreground text-center">
                        Press Enter to send • Shift+Enter for new line
                    </p>
                </div>
            </div>
        </AppLayout>
    )
}