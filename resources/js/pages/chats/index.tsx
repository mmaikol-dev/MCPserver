import AppLayout from '@/layouts/app-layout'
import { Head } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import { useEffect, useRef, useState } from 'react'
import { Loader2, Send, CheckCircle2, AlertCircle, Edit3, Plus, Trash2, Search, Eye, Mic, MicOff } from 'lucide-react'

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
    const [isListening, setIsListening] = useState(false)
    const [isRecognitionSupported, setIsRecognitionSupported] = useState(true)
    const bottomRef = useRef<HTMLDivElement>(null)
    const recognitionRef = useRef<any>(null)

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
    }, [messages])

    // Initialize Speech Recognition
    useEffect(() => {
        if (typeof window !== 'undefined') {
            const SpeechRecognition = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition

            if (SpeechRecognition) {
                const recognition = new SpeechRecognition()
                recognition.continuous = false
                recognition.interimResults = true
                recognition.lang = 'en-US'

                recognition.onstart = () => {
                    setIsListening(true)
                }

                recognition.onresult = (event: any) => {
                    const transcript = Array.from(event.results)
                        .map((result: any) => result[0])
                        .map((result: any) => result.transcript)
                        .join('')

                    setMessage(transcript)
                }

                recognition.onerror = (event: any) => {
                    console.error('Speech recognition error:', event.error)
                    setIsListening(false)

                    if (event.error === 'not-allowed') {
                        alert('Microphone access denied. Please allow microphone access in your browser settings.')
                    }
                }

                recognition.onend = () => {
                    setIsListening(false)
                }

                recognitionRef.current = recognition
            } else {
                setIsRecognitionSupported(false)
                console.warn('Speech Recognition not supported in this browser')
            }
        }

        return () => {
            if (recognitionRef.current) {
                recognitionRef.current.stop()
            }
        }
    }, [])

    const toggleListening = () => {
        if (!recognitionRef.current) return

        if (isListening) {
            recognitionRef.current.stop()
        } else {
            try {
                recognitionRef.current.start()
            } catch (error) {
                console.error('Failed to start speech recognition:', error)
            }
        }
    }

    const sendMessage = async () => {
        if (!message.trim() || loading) return

        // Stop listening if active
        if (isListening && recognitionRef.current) {
            recognitionRef.current.stop()
        }

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
                    const isView = toolName === 'view_order'

                    return (
                        <div
                            key={idx}
                            className={`rounded-md border p-3 text-xs ${hasError
                                ? 'border-destructive/50 bg-destructive/5'
                                : isDelete
                                    ? 'border-red-500/50 bg-red-500/5'
                                    : isView
                                        ? 'border-blue-500/50 bg-blue-500/5'
                                        : 'border-green-500/50 bg-green-500/5'
                                }`}
                        >
                            <div className="flex items-center gap-2 mb-2">
                                {hasError ? (
                                    <AlertCircle className="h-4 w-4 text-destructive" />
                                ) : isDelete ? (
                                    <Trash2 className="h-4 w-4 text-red-600" />
                                ) : isView ? (
                                    response?.type === 'single' ? (
                                        <Eye className="h-4 w-4 text-blue-600" />
                                    ) : (
                                        <Search className="h-4 w-4 text-blue-600" />
                                    )
                                ) : (
                                    <CheckCircle2 className="h-4 w-4 text-green-600" />
                                )}
                                <Badge variant="outline" className="text-xs flex items-center gap-1">
                                    {isCreate && <Plus className="h-3 w-3" />}
                                    {isUpdate && <Edit3 className="h-3 w-3" />}
                                    {isDelete && <Trash2 className="h-3 w-3" />}
                                    {isView && (response?.type === 'single' ? <Eye className="h-3 w-3" /> : <Search className="h-3 w-3" />)}
                                    {isCreate ? 'Create Order' :
                                        isUpdate ? 'Update Order' :
                                            isDelete ? 'Delete Order' :
                                                isView ? (response?.type === 'single' ? 'View Order' : 'Search Orders') :
                                                    toolName || 'Tool'}
                                </Badge>
                                {isView && response?.count && (
                                    <Badge variant="secondary" className="text-xs ml-auto">
                                        {response.count} {response.count === 1 ? 'result' : 'results'}
                                    </Badge>
                                )}
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

                                    {/* Show multiple orders for search results */}
                                    {isView && response?.type === 'multiple' && response?.orders && (
                                        <div className="mt-3 space-y-2">
                                            {/* Search summary */}
                                            {response.filters && response.filters !== 'none' && (
                                                <div className="text-xs text-muted-foreground bg-background/50 rounded p-2 mb-2">
                                                    <span className="font-medium">Filters:</span> {response.filters}
                                                </div>
                                            )}

                                            {/* Orders list */}
                                            <div className="space-y-2 max-h-[400px] overflow-y-auto">
                                                {response.orders.map((order: any, idx: number) => (
                                                    <div
                                                        key={idx}
                                                        className="rounded border border-border/50 bg-background/50 p-2 hover:bg-accent/5 transition-colors"
                                                    >
                                                        <div className="flex items-start justify-between gap-2 mb-1">
                                                            <span className="font-mono text-xs font-semibold text-foreground">
                                                                #{order.order_no}
                                                            </span>
                                                            <Badge
                                                                variant={
                                                                    order.status === 'Delivered' ? 'default' :
                                                                        order.status === 'Cancelled' ? 'destructive' :
                                                                            'secondary'
                                                                }
                                                                className="text-xs"
                                                            >
                                                                {order.status}
                                                            </Badge>
                                                        </div>
                                                        <div className="grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs">
                                                            <div>
                                                                <span className="text-muted-foreground">Client:</span>{' '}
                                                                <span className="text-foreground font-medium">{order.client_name}</span>
                                                            </div>
                                                            <div>
                                                                <span className="text-muted-foreground">Amount:</span>{' '}
                                                                <span className="text-foreground font-medium">
                                                                    {order.amount?.toLocaleString()} KES
                                                                </span>
                                                            </div>
                                                            <div className="col-span-2">
                                                                <span className="text-muted-foreground">Product:</span>{' '}
                                                                <span className="text-foreground">{order.product_name} (×{order.quantity})</span>
                                                            </div>
                                                            {order.city && (
                                                                <div>
                                                                    <span className="text-muted-foreground">City:</span>{' '}
                                                                    <span className="text-foreground text-xs">{order.city}</span>
                                                                </div>
                                                            )}
                                                            {order.merchant && (
                                                                <div>
                                                                    <span className="text-muted-foreground">Merchant:</span>{' '}
                                                                    <span className="text-foreground text-xs">{order.merchant}</span>
                                                                </div>
                                                            )}
                                                            {order.order_date && (
                                                                <div className="col-span-2 text-xs text-muted-foreground">
                                                                    📅 {new Date(order.order_date).toLocaleDateString()}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>

                                            {/* Summary footer */}
                                            {response.total_amount && (
                                                <div className="mt-2 pt-2 border-t border-border/50 text-xs">
                                                    <div className="flex justify-between items-center">
                                                        <span className="text-muted-foreground">Total Amount:</span>
                                                        <span className="font-semibold text-foreground">
                                                            {response.total_amount.toLocaleString()} KES
                                                        </span>
                                                    </div>
                                                </div>
                                            )}
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
        { label: 'Search Orders', prompt: 'Show me orders' },
        { label: 'View Order', prompt: 'Show order' },
        { label: 'Update Order', prompt: 'Update order' },
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
                                Create and update orders using natural language or voice
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            {!isRecognitionSupported && (
                                <Badge variant="outline" className="text-xs text-amber-600">
                                    Voice unavailable
                                </Badge>
                            )}
                            <Badge variant="outline" className="text-xs">
                                Powered by OpenRouter
                            </Badge>
                        </div>
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
                                        I can help you create and update orders using natural language or voice commands.
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
                                            <p>• "Show me order JUMANJI-042"</p>
                                            <p>• "Find all pending orders"</p>
                                            <p>• "Show orders for John Mwangi"</p>
                                            <p>• "List Adla's orders from this month"</p>
                                            <p>• "Find orders over 100k"</p>
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
                            placeholder={isListening ? "Listening..." : "Type your message or click the mic to speak..."}
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault()
                                    sendMessage()
                                }
                            }}
                            disabled={loading || isListening}
                            className={`flex-1 ${isListening ? 'border-red-500 animate-pulse' : ''}`}
                        />
                        {isRecognitionSupported && (
                            <Button
                                onClick={toggleListening}
                                disabled={loading}
                                size="icon"
                                variant={isListening ? "destructive" : "outline"}
                                className={isListening ? "animate-pulse" : ""}
                            >
                                {isListening ? (
                                    <MicOff className="h-4 w-4" />
                                ) : (
                                    <Mic className="h-4 w-4" />
                                )}
                            </Button>
                        )}
                        <Button
                            onClick={sendMessage}
                            disabled={loading || !message.trim() || isListening}
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
                        {isRecognitionSupported
                            ? "Press Enter to send • Shift+Enter for new line • Click mic to speak"
                            : "Press Enter to send • Shift+Enter for new line"
                        }
                    </p>
                </div>
            </div>
        </AppLayout>
    )
}