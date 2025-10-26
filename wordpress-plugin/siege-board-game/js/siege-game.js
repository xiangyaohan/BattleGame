// 游戏状态常量
const GAME_STATE = {
    CAMP_SELECT: 0,
    HAND_SELECT: 1,
    DUEL: 2,
    ATTACK_DEFEND_CHOICE: 3,
    BATTLE_PREPARE: 4,
    BATTLE_RESULT: 5,
    ROUND_RESULT: 6,
    GAME_OVER: 7
};

// 兵种常量
const UNIT_TYPES = {
    INFANTRY: 'infantry',  // 步兵
    ARCHER: 'archer',      // 弓兵
    CAVALRY: 'cavalry',    // 骑兵
    GENERAL: 'general',    // 武将
    CHARIOT: 'chariot'     // 战车
};

// 阵营常量
const CAMPS = {
    SHU: 'shu',  // 蜀国
    WU: 'wu'     // 吴国
};

// 游戏对象
const game = {
    // 游戏状态
    state: GAME_STATE.CAMP_SELECT,
    playerCamp: null,
    aiCamp: null,
    currentRound: 1,
    currentBattle: 1,
    shuScore: 0,
    wuScore: 0,
    shuRoundScore: 0,
    wuRoundScore: 0,
    playerDeck: [],
    playerHand: [],
    aiDeck: [],
    aiHand: [],
    playerSelectedCards: [],
    aiSelectedCards: [],
    playerDuelCard: null,
    aiDuelCard: null,
    duelWinnerIsPlayer: false,
    attacker: null,
    defender: null,
    playerBattleCards: [],
    aiBattleCards: [],
    diceResult: null,
    usedCards: [],

    // 初始化游戏
    init() {
        this.setupEventListeners();
        this.renderCampSelectScreen();
    },

    // 设置事件监听器
    setupEventListeners() {
        // 阵营选择
        document.getElementById('selectShu').addEventListener('click', () => this.selectCamp(CAMPS.SHU));
        document.getElementById('selectWu').addEventListener('click', () => this.selectCamp(CAMPS.WU));

        // 手牌选择确认按钮
        document.getElementById('confirmHandBtn').addEventListener('click', () => this.confirmHandSelection());

        // 战斗确认按钮
        document.getElementById('confirmBattleBtn').addEventListener('click', () => this.confirmBattleCards());

        // 下一步按钮
        document.getElementById('btnNextStep').addEventListener('click', () => this.nextStep());

        // 规则说明
        document.getElementById('btnRules').addEventListener('click', () => this.showRulesModal());
        document.getElementById('closeRulesBtn').addEventListener('click', () => this.hideRulesModal());

        // 重新开始
        document.getElementById('btnRestart').addEventListener('click', () => this.restartGame());
        document.getElementById('playAgainBtn').addEventListener('click', () => this.restartGame());

        // 攻守选择按钮
        document.getElementById('chooseAttacker').addEventListener('click', () => {
            this.chooseAttackDefend(true);
        });
        
        document.getElementById('chooseDefender').addEventListener('click', () => {
            this.chooseAttackDefend(false);
        });

        // 阵营选择卡片翻转效果
        document.getElementById('selectShu').addEventListener('mouseenter', function() {
            this.querySelector('.relative').style.transform = 'rotateY(180deg)';
        });
        document.getElementById('selectShu').addEventListener('mouseleave', function() {
            this.querySelector('.relative').style.transform = 'rotateY(0deg)';
        });
        document.getElementById('selectWu').addEventListener('mouseenter', function() {
            this.querySelector('.relative').style.transform = 'rotateY(180deg)';
        });
        document.getElementById('selectWu').addEventListener('mouseleave', function() {
            this.querySelector('.relative').style.transform = 'rotateY(0deg)';
        });
    },

    // 选择阵营
    selectCamp(camp) {
        this.playerCamp = camp;
        this.aiCamp = camp === CAMPS.SHU ? CAMPS.WU : CAMPS.SHU;
        
        // 初始化牌堆
        this.initializeDecks();
        
        // 切换到手牌选择界面
        this.state = GAME_STATE.HAND_SELECT;
        this.renderHandSelectScreen();
    },

    // 初始化牌堆
    initializeDecks() {
        // 创建25张牌的牌堆：5种兵种，每种5张，战斗力从1-5
        this.playerDeck = [];
        this.aiDeck = [];
        
        const unitIcons = {
            [UNIT_TYPES.INFANTRY]: 'user',
            [UNIT_TYPES.ARCHER]: 'arrow',
            [UNIT_TYPES.CAVALRY]: 'horse',
            [UNIT_TYPES.GENERAL]: 'star',
            [UNIT_TYPES.CHARIOT]: 'truck'
        };

        const unitNames = {
            [UNIT_TYPES.INFANTRY]: '步兵',
            [UNIT_TYPES.ARCHER]: '弓兵',
            [UNIT_TYPES.CAVALRY]: '骑兵',
            [UNIT_TYPES.GENERAL]: '武将',
            [UNIT_TYPES.CHARIOT]: '战车'
        };
        
        // 为每种兵种创建5张牌，战斗力从1到5
        Object.keys(UNIT_TYPES).forEach(typeKey => {
            const type = UNIT_TYPES[typeKey];
            for (let power = 1; power <= 5; power++) {
                const card = {
                    id: `${type}_${power}_${Math.random().toString(36).substr(2, 9)}`,
                    type: type,
                    typeName: unitNames[type],
                    power: power,
                    icon: unitIcons[type],
                    camp: this.playerCamp
                };
                this.playerDeck.push({...card});
                this.aiDeck.push({...card, camp: this.aiCamp});
            }
        });
        
        // 随机排序牌堆
        this.shuffleDeck(this.playerDeck);
        this.shuffleDeck(this.aiDeck);
    },

    // 洗牌
    shuffleDeck(deck) {
        for (let i = deck.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [deck[i], deck[j]] = [deck[j], deck[i]];
        }
    },

    // 渲染阵营选择界面
    renderCampSelectScreen() {
        document.getElementById('campSelectScreen').classList.remove('hidden');
        document.getElementById('handSelectScreen').classList.add('hidden');
        document.getElementById('gameScreen').classList.add('hidden');
        document.getElementById('gameOverScreen').classList.add('hidden');
        
        document.getElementById('gameStatus').textContent = '选择阵营开始游戏';
    },

    // 渲染手牌选择界面
    renderHandSelectScreen() {
        document.getElementById('campSelectScreen').classList.add('hidden');
        document.getElementById('handSelectScreen').classList.remove('hidden');
        document.getElementById('gameScreen').classList.add('hidden');
        document.getElementById('gameOverScreen').classList.add('hidden');
        
        document.getElementById('gameStatus').textContent = '请从牌库中选择5-10张手牌 (建议平衡各兵种)';
        
        // 渲染玩家牌堆
        const playerDeckEl = document.getElementById('playerDeck');
        playerDeckEl.innerHTML = '';
        
        this.playerDeck.forEach(card => {
            const cardEl = this.createCardElement(card, true);
            cardEl.addEventListener('click', () => this.toggleCardSelection(card));
            playerDeckEl.appendChild(cardEl);
        });
        
        // 清空已选卡片区域
        document.getElementById('selectedCards').innerHTML = '';
        document.getElementById('selectedCount').textContent = '0';
        document.getElementById('confirmHandBtn').disabled = true;
    },

    // 创建卡片元素
    createCardElement(card, isSelectable = false) {
        const cardColor = card.camp === CAMPS.SHU ? 'bg-shu/10 text-shu border-shu' : 'bg-wu/10 text-wu border-wu';
        const selectableClass = isSelectable ? 'cursor-pointer hover:opacity-80' : '';
        
        const cardEl = document.createElement('div');
        cardEl.className = `relative w-full aspect-[3/4] rounded-lg border-2 ${cardColor} ${selectableClass} flex flex-col p-2 card-shadow card-hover`;
        cardEl.dataset.cardId = card.id;
        
        // 战斗力
        const powerEl = document.createElement('div');
        powerEl.className = 'text-xl font-bold';
        powerEl.textContent = card.power;
        
        // 兵种图标
        const iconEl = document.createElement('div');
        iconEl.className = 'flex-grow flex items-center justify-center text-4xl';
        iconEl.innerHTML = `<i class="fa fa-${card.icon}"></i>`;
        
        // 兵种名称
        const nameEl = document.createElement('div');
        nameEl.className = 'text-center font-semibold';
        nameEl.textContent = card.typeName;
        
        cardEl.appendChild(powerEl);
        cardEl.appendChild(iconEl);
        cardEl.appendChild(nameEl);
        
        return cardEl;
    },

    // 切换卡片选择状态
    toggleCardSelection(card) {
        const index = this.playerSelectedCards.findIndex(c => c.id === card.id);
        
        if (index > -1) {
            // 取消选择
            this.playerSelectedCards.splice(index, 1);
            const cardEl = document.querySelector(`[data-card-id="${card.id}"]`);
            if (cardEl) {
                cardEl.classList.remove('ring-4', 'ring-yellow-400');
            }
        } else {
            // 选择卡片，但不超过10张
            if (this.playerSelectedCards.length < 10) {
                this.playerSelectedCards.push(card);
                const cardEl = document.querySelector(`[data-card-id="${card.id}"]`);
                if (cardEl) {
                    cardEl.classList.add('ring-4', 'ring-yellow-400');
                }
            }
        }
        
        // 更新已选卡片显示
        this.renderSelectedCards();
        
        // 启用/禁用确认按钮
        document.getElementById('confirmHandBtn').disabled = this.playerSelectedCards.length < 5 || this.playerSelectedCards.length > 10;
    },

    // 渲染已选卡片
    renderSelectedCards() {
        const selectedCardsEl = document.getElementById('selectedCards');
        selectedCardsEl.innerHTML = '';
        
        this.playerSelectedCards.forEach(card => {
            const cardEl = this.createCardElement(card, false);
            selectedCardsEl.appendChild(cardEl);
        });
        
        document.getElementById('selectedCount').textContent = this.playerSelectedCards.length;
    },

    // 确认手牌选择
    confirmHandSelection() {
        if (this.playerSelectedCards.length < 5 || this.playerSelectedCards.length > 10) {
            alert('请选择5-10张手牌！');
            return;
        }
        
        // 确认对话框
        const cardTypes = {};
        this.playerSelectedCards.forEach(card => {
            cardTypes[card.type] = (cardTypes[card.type] || 0) + 1;
        });
        
        const typeNames = {
            'infantry': '步兵',
            'archer': '弓兵', 
            'cavalry': '骑兵',
            'general': '武将',
            'chariot': '战车'
        };
        
        const composition = Object.entries(cardTypes)
            .map(([type, count]) => `${typeNames[type]}${count}张`)
            .join('，');
            
        if (!confirm(`确认选择这${this.playerSelectedCards.length}张手牌吗？\n组成：${composition}\n\n选择后将开始第1局决斗！`)) {
            return;
        }
        
        // 设置玩家手牌
        this.playerHand = [...this.playerSelectedCards];
        
        // 从牌堆中移除已选手牌
        this.playerDeck = this.playerDeck.filter(card => !this.playerSelectedCards.some(selected => selected.id === card.id));
        
        // AI选择手牌（随机选择7张）
        this.aiHand = [];
        const tempDeck = [...this.aiDeck];
        this.shuffleDeck(tempDeck);
        for (let i = 0; i < 7 && i < tempDeck.length; i++) {
            this.aiHand.push(tempDeck[i]);
        }
        
        // 从AI牌堆中移除已选手牌
        this.aiDeck = this.aiDeck.filter(card => !this.aiHand.some(selected => selected.id === card.id));
        
        // 进入决斗阶段
        this.state = GAME_STATE.DUEL;
        this.renderGameScreen();
        this.startDuel();
    },

    // 渲染游戏主界面
    renderGameScreen() {
        document.getElementById('campSelectScreen').classList.add('hidden');
        document.getElementById('handSelectScreen').classList.add('hidden');
        document.getElementById('gameScreen').classList.remove('hidden');
        document.getElementById('gameOverScreen').classList.add('hidden');
        
        // 更新玩家和对手信息
        document.getElementById('playerName').textContent = this.playerCamp === CAMPS.SHU ? '蜀国 (你)' : '吴国 (你)';
        document.getElementById('opponentName').textContent = this.aiCamp === CAMPS.SHU ? '蜀国 (对手)' : '吴国 (对手)';
        document.getElementById('playerHandCount').textContent = this.playerHand.length;
        document.getElementById('opponentHandCount').textContent = this.aiHand.length;
        
        // 更新分数显示
        document.getElementById('shuScore').textContent = this.shuScore;
        document.getElementById('wuScore').textContent = this.wuScore;
        document.getElementById('shuRoundScore').textContent = this.shuRoundScore;
        document.getElementById('wuRoundScore').textContent = this.wuRoundScore;
        document.getElementById('currentRound').textContent = `${this.currentRound}/3`;
        document.getElementById('battleRoundInfo').textContent = `第${this.currentBattle}场战斗`;
        
        // 渲染玩家手牌
        this.renderPlayerHand();
        
        // 清空战斗区域
        document.getElementById('opponentBattleCards').innerHTML = '';
        document.getElementById('opponentBattlePower').textContent = '';
        document.getElementById('battleStatus').textContent = '准备战斗';
        document.getElementById('duelResult').classList.add('hidden');
        document.getElementById('attackDefendChoice').classList.add('hidden');
        document.getElementById('battleResult').classList.add('hidden');
        
        // 隐藏按钮
        document.getElementById('confirmBattleBtn').classList.add('hidden');
        document.getElementById('btnNextStep').classList.add('hidden');
    },

    // 渲染玩家手牌
    renderPlayerHand() {
        const playerHandEl = document.getElementById('playerHand');
        playerHandEl.innerHTML = '';
        
        this.playerHand.forEach(card => {
            const cardEl = this.createCardElement(card, true);
            cardEl.addEventListener('click', () => {
                if (this.state === GAME_STATE.DUEL) {
                    this.selectDuelCard(card);
                } else if (this.state === GAME_STATE.BATTLE_PREPARE) {
                    this.toggleBattleCardSelection(card);
                }
            });
            playerHandEl.appendChild(cardEl);
        });
    },

    // 开始决斗
    startDuel() {
        document.getElementById('gameStatus').textContent = `第${this.currentRound}局 - 请选择一张牌进行决斗`;
        document.getElementById('battleStatus').textContent = '选择决斗卡牌 (决定攻守方)';
        
        // 重置决斗状态
        this.playerDuelCard = null;
        this.aiDuelCard = null;
        this.playerSelectedCards = [];
        this.aiSelectedCards = [];
    },

    // 选择决斗卡牌
    selectDuelCard(card) {
        if (this.playerDuelCard) {
            // 取消之前的选择
            const prevCardEl = document.querySelector(`[data-card-id="${this.playerDuelCard.id}"]`);
            if (prevCardEl) {
                prevCardEl.classList.remove('ring-4', 'ring-yellow-400');
            }
        }
        
        // 选择新卡片
        this.playerDuelCard = card;
        const cardEl = document.querySelector(`[data-card-id="${card.id}"]`);
        if (cardEl) {
            cardEl.classList.add('ring-4', 'ring-yellow-400');
        }
        
        // AI选择决斗卡片（策略性选择）
        this.aiDuelCard = this.aiChooseDuelCard();
        
        // 显示下一步按钮
        document.getElementById('btnNextStep').classList.remove('hidden');
    },

    // AI选择决斗卡牌的策略
    aiChooseDuelCard() {
        // 按战斗力排序
        const sortedCards = [...this.aiHand].sort((a, b) => b.power - a.power);
        
        // 策略：
        // 1. 如果是第一局第一场战斗，倾向于使用中等强度的卡牌
        // 2. 如果手牌中有很多强卡，可以用一张中等的
        // 3. 如果手牌整体较弱，使用最强的
        
        const strongCards = sortedCards.filter(card => card.power >= 4);
        const mediumCards = sortedCards.filter(card => card.power >= 2 && card.power <= 3);
        const weakCards = sortedCards.filter(card => card.power <= 1);
        
        // 如果是第一局且有中等卡牌，30%概率使用中等卡牌
        if (this.currentRound === 1 && mediumCards.length > 0 && Math.random() < 0.3) {
            return mediumCards[Math.floor(Math.random() * mediumCards.length)];
        }
        
        // 如果强卡很多（3张以上），40%概率保留最强的，使用次强的
        if (strongCards.length >= 3 && Math.random() < 0.4) {
            return sortedCards[1]; // 使用第二强的卡牌
        }
        
        // 如果手牌整体较弱，使用最强的
        if (strongCards.length <= 1) {
            return sortedCards[0];
        }
        
        // 默认情况：70%概率使用最强的，30%概率使用次强的
        return Math.random() < 0.7 ? sortedCards[0] : sortedCards[1];
    },

    // 下一步
    nextStep() {
        if (this.state === GAME_STATE.DUEL) {
            this.resolveDuel();
        } else if (this.state === GAME_STATE.BATTLE_RESULT) {
            this.checkBattleEnd();
        } else if (this.state === GAME_STATE.ROUND_RESULT) {
            this.checkRoundEnd();
        }
    },

    // 解决决斗
    resolveDuel() {
        if (!this.playerDuelCard || !this.aiDuelCard) {
            return;
        }
        
        // 显示决斗结果
        const duelResultEl = document.getElementById('duelResult');
        duelResultEl.classList.remove('hidden');
        
        let resultText = '';
        let winner = null;
        
        // 比较战斗力
        if (this.playerDuelCard.power > this.aiDuelCard.power) {
            winner = this.playerCamp;
            this.duelWinnerIsPlayer = true;
            resultText = `你赢得了决斗！`;
        } else if (this.playerDuelCard.power < this.aiDuelCard.power) {
            winner = this.aiCamp;
            this.duelWinnerIsPlayer = false;
            resultText = `对手赢得了决斗！`;
        } else {
            // 平局，掷骰子
            const playerDice = Math.floor(Math.random() * 6) + 1;
            const aiDice = Math.floor(Math.random() * 6) + 1;
            this.diceResult = { player: playerDice, ai: aiDice };
            
            if (playerDice > aiDice) {
                winner = this.playerCamp;
                this.duelWinnerIsPlayer = true;
                resultText = `决斗平局！骰子结果：你 ${playerDice} 点，对手 ${aiDice} 点。你赢得了决斗！`;
            } else {
                winner = this.aiCamp;
                this.duelWinnerIsPlayer = false;
                resultText = `决斗平局！骰子结果：你 ${playerDice} 点，对手 ${aiDice} 点。对手赢得了决斗！`;
            }
        }
        
        // 显示决斗卡牌
        const opponentBattleCardsEl = document.getElementById('opponentBattleCards');
        opponentBattleCardsEl.innerHTML = '';
        
        // 显示AI的决斗卡牌
        const aiCardEl = this.createCardElement(this.aiDuelCard, false);
        opponentBattleCardsEl.appendChild(aiCardEl);
        
        // 显示玩家的决斗卡牌（从手牌中移除并添加到战斗区域）
        this.playerHand = this.playerHand.filter(card => card.id !== this.playerDuelCard.id);
        this.aiHand = this.aiHand.filter(card => card.id !== this.aiDuelCard.id);
        
        // 将决斗卡牌加入战斗卡牌
        this.playerBattleCards = [this.playerDuelCard];
        this.aiBattleCards = [this.aiDuelCard];
        
        duelResultEl.innerHTML = `
            <div class="p-4 bg-gray-100 rounded-lg">
                <p class="font-bold mb-2">决斗结果</p>
                <p>${resultText}</p>
                <div class="flex justify-center mt-2">
                    <div class="mr-4">
                        <p class="text-sm text-gray-500">你的卡牌</p>
                        <p class="font-bold">${this.playerDuelCard.typeName} (战斗力: ${this.playerDuelCard.power})</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">对手的卡牌</p>
                        <p class="font-bold">${this.aiDuelCard.typeName} (战斗力: ${this.aiDuelCard.power})</p>
                    </div>
                </div>
            </div>
        `;
        
        // 隐藏下一步按钮
        document.getElementById('btnNextStep').classList.add('hidden');
        
        // 根据获胜者决定下一步
        if (this.duelWinnerIsPlayer) {
            // 玩家获胜，显示攻守选择界面
            this.state = GAME_STATE.ATTACK_DEFEND_CHOICE;
            document.getElementById('attackDefendChoice').classList.remove('hidden');
        } else {
            // AI获胜，AI自动选择攻守方
            this.aiChooseAttackDefend();
        }
    },

    // 玩家选择攻守方
    chooseAttackDefend(isAttacker) {
        // 确认选择
        const role = isAttacker ? '攻方' : '守方';
        const advantage = isAttacker ? '攻方优势：知道守方出牌数量后再出牌' : '守方优势：可以控制战斗规模(1-5张牌)';
        
        if (!confirm(`确认选择作为${role}吗？\n\n${advantage}\n\n选择后将开始第1场战斗！`)) {
            return;
        }
        
        if (isAttacker) {
            this.attacker = this.playerCamp;
            this.defender = this.aiCamp;
        } else {
            this.attacker = this.aiCamp;
            this.defender = this.playerCamp;
        }
        
        // 隐藏选择界面
        document.getElementById('attackDefendChoice').classList.add('hidden');
        
        // 更新决斗结果显示
        const duelResultEl = document.getElementById('duelResult');
        const currentContent = duelResultEl.innerHTML;
        duelResultEl.innerHTML = currentContent.replace(
            '</div>',
            `<p class="mt-2 text-blue-600 font-bold">你选择了作为${role}！</p></div>`
        );
        
        // 开始战斗准备
        this.state = GAME_STATE.BATTLE_PREPARE;
        this.prepareBattle();
    },

    // AI选择攻守方
    aiChooseAttackDefend() {
        // AI策略：根据手牌情况和战斗力决定
        const aiHandPower = this.aiHand.reduce((sum, card) => sum + card.power, 0);
        const playerHandPower = this.playerHand.reduce((sum, card) => sum + card.power, 0);
        
        // 如果AI手牌战斗力更强，倾向于选择攻方；否则选择守方
        const shouldBeAttacker = aiHandPower > playerHandPower ? Math.random() > 0.3 : Math.random() > 0.7;
        
        if (shouldBeAttacker) {
            this.attacker = this.aiCamp;
            this.defender = this.playerCamp;
        } else {
            this.attacker = this.playerCamp;
            this.defender = this.aiCamp;
        }
        
        // 更新决斗结果显示
        const duelResultEl = document.getElementById('duelResult');
        const currentContent = duelResultEl.innerHTML;
        duelResultEl.innerHTML = currentContent.replace(
            '</div>',
            `<p class="mt-2 text-red-600 font-bold">对手选择了作为${shouldBeAttacker ? '攻方' : '守方'}！</p></div>`
        );
        
        // 延迟一下让玩家看到AI的选择
        setTimeout(() => {
            this.state = GAME_STATE.BATTLE_PREPARE;
            this.prepareBattle();
        }, 1500);
    },

    // AI选择守方卡牌的策略
    aiChooseDefenderCards() {
        // 策略考虑因素：
        // 1. 当前战斗轮次（第3场更重要）
        // 2. 手牌质量
        // 3. 当前比分情况
        
        const handPower = this.aiHand.reduce((sum, card) => sum + card.power, 0);
        const avgPower = handPower / this.aiHand.length;
        
        let cardCount;
        
        // 根据战斗轮次和手牌情况决定出牌数量
        if (this.currentBattle === 3) {
            // 第3场战斗，更重要，倾向于出更多牌
            cardCount = Math.random() < 0.6 ? Math.min(4, this.aiHand.length) : Math.min(5, this.aiHand.length);
        } else if (this.currentBattle === 1) {
            // 第1场战斗，保守一些
            cardCount = Math.random() < 0.4 ? 2 : 3;
        } else {
            // 第2场战斗，中等策略
            cardCount = Math.random() < 0.5 ? 3 : 4;
        }
        
        // 确保不超过手牌数量
        cardCount = Math.min(cardCount, this.aiHand.length);
        
        // 选择卡牌策略：混合强卡和中等卡
        const sortedCards = [...this.aiHand].sort((a, b) => b.power - a.power);
        const selectedCards = [];
        
        // 选择一些强卡
        const strongCardCount = Math.min(Math.ceil(cardCount * 0.6), sortedCards.length);
        for (let i = 0; i < strongCardCount; i++) {
            selectedCards.push(sortedCards[i]);
        }
        
        // 如果还需要更多卡牌，随机选择剩余的
        const remainingCards = this.aiHand.filter(card => !selectedCards.some(selected => selected.id === card.id));
        const remainingCount = cardCount - selectedCards.length;
        
        for (let i = 0; i < remainingCount && i < remainingCards.length; i++) {
            const randomIndex = Math.floor(Math.random() * remainingCards.length);
            if (!selectedCards.some(selected => selected.id === remainingCards[randomIndex].id)) {
                selectedCards.push(remainingCards[randomIndex]);
                remainingCards.splice(randomIndex, 1);
            }
        }
        
        return { cardCount, selectedCards };
    },

    // AI选择攻方卡牌的策略
    aiChooseAttackerCards(count) {
        // 攻方策略：需要考虑守方可能的战斗力
        // 估算守方战斗力（基于已知信息）
        const playerHandSize = this.playerHand.length;
        const estimatedPlayerAvgPower = 3; // 假设玩家平均卡牌战斗力
        
        const sortedCards = [...this.aiHand].sort((a, b) => b.power - a.power);
        const selectedCards = [];
        
        // 根据战斗轮次调整策略
        if (this.currentBattle === 3) {
            // 第3场战斗，使用最强的卡牌
            for (let i = 0; i < count && i < sortedCards.length; i++) {
                selectedCards.push(sortedCards[i]);
            }
        } else {
            // 前两场战斗，平衡使用强卡和中等卡
            const strongCardCount = Math.min(Math.ceil(count * 0.7), sortedCards.length);
            
            // 选择强卡
            for (let i = 0; i < strongCardCount; i++) {
                selectedCards.push(sortedCards[i]);
            }
            
            // 选择中等卡
            const remainingCards = this.aiHand.filter(card => !selectedCards.some(selected => selected.id === card.id));
            const remainingCount = count - selectedCards.length;
            
            for (let i = 0; i < remainingCount && i < remainingCards.length; i++) {
                selectedCards.push(remainingCards[i]);
            }
        }
        
        return selectedCards;
    },

    // 准备战斗
    prepareBattle() {
        // 更新战斗信息显示
        const attackerName = this.attacker === this.playerCamp ? '你' : '对手';
        const defenderName = this.defender === this.playerCamp ? '你' : '对手';
        
        // 更新战斗轮次信息
        const battlePoints = this.currentBattle === 3 ? '2分' : '1分';
        document.getElementById('battleRoundInfo').textContent = `第${this.currentBattle}场战斗 (攻方: ${attackerName}, 守方: ${defenderName}) - 获胜得${battlePoints}`;
        
        if (this.defender === this.playerCamp) {
            document.getElementById('gameStatus').textContent = `第${this.currentRound}局第${this.currentBattle}场 - 你是守方，请选择1-5张牌进行战斗`;
            document.getElementById('battleStatus').textContent = '守方先出牌，选择1-5张牌 (守方优势：可控制战斗规模)';
        } else {
            document.getElementById('gameStatus').textContent = `第${this.currentRound}局第${this.currentBattle}场 - 对手是守方，请等待对手出牌`;
            document.getElementById('battleStatus').textContent = '对手是守方，正在思考出牌策略...';
            
            // AI作为守方，延迟出牌
            setTimeout(() => {
                this.aiPlayDefenderCards();
            }, 1500);
        }
        
        // 重置战斗卡牌选择
        this.playerSelectedCards = [];
        this.aiSelectedCards = [];
    },

    // 切换战斗卡牌选择
    toggleBattleCardSelection(card) {
        // 只有守方可以选择任意数量的牌，攻方必须与守方数量相同
        if (this.defender === this.playerCamp) {
            const index = this.playerSelectedCards.findIndex(c => c.id === card.id);
            
            if (index > -1) {
                // 取消选择
                this.playerSelectedCards.splice(index, 1);
                const cardEl = document.querySelector(`[data-card-id="${card.id}"]`);
                if (cardEl) {
                    cardEl.classList.remove('ring-4', 'ring-yellow-400');
                }
            } else {
                // 选择卡片，但不超过5张
                if (this.playerSelectedCards.length < 5) {
                    this.playerSelectedCards.push(card);
                    const cardEl = document.querySelector(`[data-card-id="${card.id}"]`);
                    if (cardEl) {
                        cardEl.classList.add('ring-4', 'ring-yellow-400');
                    }
                }
            }
            
            // 启用/禁用确认按钮
            document.getElementById('confirmBattleBtn').disabled = this.playerSelectedCards.length === 0;
            document.getElementById('confirmBattleBtn').classList.toggle('hidden', this.playerSelectedCards.length === 0);
        }
    },

    // AI作为守方出牌
    aiPlayDefenderCards() {
        // AI策略性选择卡牌数量和卡牌
        const { cardCount, selectedCards } = this.aiChooseDefenderCards();
        
        this.aiSelectedCards = selectedCards;
        
        // 从AI手牌中移除已选卡牌
        this.aiHand = this.aiHand.filter(card => !this.aiSelectedCards.some(selected => selected.id === card.id));
        
        // 将选择的卡牌加入战斗卡牌
        this.aiBattleCards.push(...this.aiSelectedCards);
        
        // 显示AI出牌数量
        const opponentBattleCardsEl = document.getElementById('opponentBattleCards');
        opponentBattleCardsEl.innerHTML = '';
        
        for (let i = 0; i < this.aiBattleCards.length; i++) {
            const cardBackEl = this.createCardBackElement(this.aiCamp);
            opponentBattleCardsEl.appendChild(cardBackEl);
        }
        
        // 切换到玩家作为攻方出牌
        document.getElementById('gameStatus').textContent = `第${this.currentRound}局第${this.currentBattle}场 - 你是攻方，请选择${this.aiSelectedCards.length}张牌进行战斗`;
        document.getElementById('battleStatus').textContent = `攻方必须出${this.aiSelectedCards.length}张牌 (与守方数量相同)`;
        
        // 重置玩家选择
        this.playerSelectedCards = [];
    },

    // 确认战斗卡牌
    confirmBattleCards() {
        if (this.defender === this.playerCamp) {
            // 玩家作为守方
            if (this.playerSelectedCards.length === 0) {
                return;
            }
            
            // 从玩家手牌中移除已选卡牌
            this.playerHand = this.playerHand.filter(card => !this.playerSelectedCards.some(selected => selected.id === card.id));
            
            // 将选择的卡牌加入战斗卡牌
            this.playerBattleCards.push(...this.playerSelectedCards);
            
            // 显示下一步按钮
            document.getElementById('confirmBattleBtn').classList.add('hidden');
            document.getElementById('btnNextStep').classList.remove('hidden');
            
            // AI作为攻方，选择相同数量的牌
            setTimeout(() => {
                this.aiPlayAttackerCards(this.playerSelectedCards.length);
                this.resolveBattle();
            }, 1000);
        } else {
            // 玩家作为攻方
            if (this.playerSelectedCards.length !== this.aiSelectedCards.length) {
                alert('请选择' + this.aiSelectedCards.length + '张牌');
                return;
            }
            
            // 从玩家手牌中移除已选卡牌
            this.playerHand = this.playerHand.filter(card => !this.playerSelectedCards.some(selected => selected.id === card.id));
            
            // 将选择的卡牌加入战斗卡牌
            this.playerBattleCards.push(...this.playerSelectedCards);
            
            // 显示下一步按钮
            document.getElementById('confirmBattleBtn').classList.add('hidden');
            document.getElementById('btnNextStep').classList.remove('hidden');
            
            // 解决战斗
            this.resolveBattle();
        }
    },

    // AI作为攻方出牌
    aiPlayAttackerCards(count) {
        // AI策略性选择攻方卡牌
        const selectedCards = this.aiChooseAttackerCards(count);
        this.aiSelectedCards = selectedCards;
        
        // 从AI手牌中移除已选卡牌
        this.aiHand = this.aiHand.filter(card => !this.aiSelectedCards.some(selected => selected.id === card.id));
        
        // 将选择的卡牌加入战斗卡牌
        this.aiBattleCards.push(...this.aiSelectedCards);
    },

    // 解决战斗
    resolveBattle() {
        // 计算双方战斗力总和
        const playerPower = this.playerBattleCards.reduce((sum, card) => sum + card.power, 0);
        const aiPower = this.aiBattleCards.reduce((sum, card) => sum + card.power, 0);
        
        // 检查攻方牌数量是否不足守方（根据规则，只有攻方牌数不足时才受惩罚）
        const playerCardCount = this.playerBattleCards.length;
        const aiCardCount = this.aiBattleCards.length;
        
        let adjustedPlayerPower = playerPower;
        let adjustedAiPower = aiPower;
        let powerAdjustment = 0;
        
        if (this.attacker === this.playerCamp && playerCardCount < aiCardCount) {
            // 玩家作为攻方，牌数量少于守方，每少1张牌扣除1点战斗力
            powerAdjustment = aiCardCount - playerCardCount;
            adjustedPlayerPower -= powerAdjustment;
        } else if (this.attacker === this.aiCamp && aiCardCount < playerCardCount) {
            // AI作为攻方，牌数量少于守方，每少1张牌扣除1点战斗力
            powerAdjustment = playerCardCount - aiCardCount;
            adjustedAiPower -= powerAdjustment;
        }
        
        // 决定战斗结果
        let battleWinner = null;
        let resultText = '';
        
        if (adjustedPlayerPower > adjustedAiPower) {
            battleWinner = this.playerCamp;
            resultText = '你赢得了这场战斗！';
        } else if (adjustedPlayerPower < adjustedAiPower) {
            battleWinner = this.aiCamp;
            resultText = '对手赢得了这场战斗！';
        } else {
            resultText = '战斗平局！';
        }
        
        // 更新分数
        if (battleWinner) {
            const points = this.currentBattle === 3 ? 2 : 1; // 第三场战斗获胜得2分
            
            if (battleWinner === CAMPS.SHU) {
                this.shuRoundScore += points;
            } else {
                this.wuRoundScore += points;
            }
        }
        
        // 显示战斗结果
        const battleResultEl = document.getElementById('battleResult');
        battleResultEl.classList.remove('hidden');
        
        // 显示AI的战斗卡牌
        const opponentBattleCardsEl = document.getElementById('opponentBattleCards');
        opponentBattleCardsEl.innerHTML = '';
        
        this.aiBattleCards.forEach(card => {
            const cardEl = this.createCardElement(card, false);
            opponentBattleCardsEl.appendChild(cardEl);
        });
        
        // 显示AI战斗力
        document.getElementById('opponentBattlePower').textContent = `战斗力总和: ${aiPower} ${adjustedAiPower !== aiPower ? `(调整后: ${adjustedAiPower})` : ''}`;
        
        // 构建战斗力调整说明
        let adjustmentText = '';
        if (powerAdjustment > 0) {
            const attackerName = this.attacker === this.playerCamp ? '你' : '对手';
            adjustmentText = `<p class="text-sm text-orange-600 mt-1">${attackerName}作为攻方牌数不足，扣除${powerAdjustment}点战斗力</p>`;
        }
        
        battleResultEl.innerHTML = `
            <div class="p-4 bg-gray-100 rounded-lg">
                <p class="font-bold mb-2">第${this.currentBattle}场战斗结果</p>
                <p class="text-lg font-semibold ${battleWinner === this.playerCamp ? 'text-green-600' : battleWinner === this.aiCamp ? 'text-red-600' : 'text-gray-600'}">${resultText}</p>
                <div class="flex justify-center mt-2">
                    <div class="mr-4 text-center">
                        <p class="text-sm text-gray-500">你的战斗力</p>
                        <p class="font-bold">${playerPower} ${adjustedPlayerPower !== playerPower ? `→ ${adjustedPlayerPower}` : ''}</p>
                        <p class="text-xs text-gray-400">${playerCardCount}张牌</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500">对手的战斗力</p>
                        <p class="font-bold">${aiPower} ${adjustedAiPower !== aiPower ? `→ ${adjustedAiPower}` : ''}</p>
                        <p class="text-xs text-gray-400">${aiCardCount}张牌</p>
                    </div>
                </div>
                ${adjustmentText}
                <div class="mt-3 pt-2 border-t border-gray-300">
                    <p class="text-sm text-gray-500">本局得分情况</p>
                    <p class="font-bold">你: ${this.playerCamp === CAMPS.SHU ? this.shuRoundScore : this.wuRoundScore} 分 vs 对手: ${this.aiCamp === CAMPS.SHU ? this.shuRoundScore : this.wuRoundScore} 分</p>
                </div>
            </div>
        `;
        
        // 更新游戏状态
        this.state = GAME_STATE.BATTLE_RESULT;
        
        // 将战斗卡牌加入已使用卡牌
        this.usedCards.push(...this.playerBattleCards, ...this.aiBattleCards);
        
        // 更新显示
        this.renderGameScreen();
        document.getElementById('btnNextStep').classList.remove('hidden');
    },

    // 检查战斗是否结束
    checkBattleEnd() {
        // 重置战斗卡牌
        this.playerBattleCards = [];
        this.aiBattleCards = [];
        
        // 增加战斗次数
        this.currentBattle++;
        
        if (this.currentBattle <= 3) {
            // 还有战斗，攻守互换
            [this.attacker, this.defender] = [this.defender, this.attacker];
            
            // 开始下一场战斗
            this.state = GAME_STATE.BATTLE_PREPARE;
            this.renderGameScreen();
            this.prepareBattle();
        } else {
            // 战斗全部结束，显示回合结果
            this.state = GAME_STATE.ROUND_RESULT;
            this.showRoundResult();
        }
    },

    // 显示回合结果
    showRoundResult() {
        let roundWinner = null;
        let resultText = '';
        
        if (this.shuRoundScore > this.wuRoundScore) {
            roundWinner = CAMPS.SHU;
            resultText = '蜀国赢得了本局！';
        } else if (this.shuRoundScore < this.wuRoundScore) {
            roundWinner = CAMPS.WU;
            resultText = '吴国赢得了本局！';
        } else {
            resultText = '本局平局！';
        }
        
        // 更新大比分
        if (roundWinner) {
            if (roundWinner === CAMPS.SHU) {
                this.shuScore++;
            } else {
                this.wuScore++;
            }
        }
        
        // 显示回合结果
        document.getElementById('battleResult').innerHTML = `
            <div class="p-4 bg-gray-100 rounded-lg">
                <p class="font-bold mb-2">本局结果</p>
                <p>${resultText}</p>
                <div class="mt-2">
                    <p class="text-sm text-gray-500">本局得分</p>
                    <p class="font-bold">蜀国: ${this.shuRoundScore} 分 vs 吴国: ${this.wuRoundScore} 分</p>
                </div>
                <div class="mt-2">
                    <p class="text-sm text-gray-500">大比分</p>
                    <p class="font-bold">蜀国: ${this.shuScore} 分 vs 吴国: ${this.wuScore} 分</p>
                </div>
            </div>
        `;
        
        // 更新显示
        this.renderGameScreen();
        document.getElementById('btnNextStep').classList.remove('hidden');
    },

    // 检查回合是否结束
    checkRoundEnd() {
        // 检查游戏是否结束
        if (this.shuScore >= 2 || this.wuScore >= 2) {
            this.state = GAME_STATE.GAME_OVER;
            this.showGameOver();
        } else if (this.currentRound < 3) {
            // 还有回合，重置回合状态
            this.currentRound++;
            this.currentBattle = 1;
            this.shuRoundScore = 0;
            this.wuRoundScore = 0;
            
            // 进入下一轮的手牌选择
            this.state = GAME_STATE.HAND_SELECT;
            this.playerSelectedCards = [];
            this.renderHandSelectScreen();
        } else {
            // 所有回合结束
            this.state = GAME_STATE.GAME_OVER;
            this.showGameOver();
        }
    },

    // 显示游戏结束
    showGameOver() {
        document.getElementById('campSelectScreen').classList.add('hidden');
        document.getElementById('handSelectScreen').classList.add('hidden');
        document.getElementById('gameScreen').classList.add('hidden');
        document.getElementById('gameOverScreen').classList.remove('hidden');
        
        let winnerText = '';
        
        if (this.shuScore > this.wuScore) {
            winnerText = this.playerCamp === CAMPS.SHU ? '恭喜你获胜！' : '对手获胜！';
        } else if (this.shuScore < this.wuScore) {
            winnerText = this.playerCamp === CAMPS.WU ? '恭喜你获胜！' : '对手获胜！';
        } else {
            winnerText = '游戏平局！';
        }
        
        document.getElementById('winnerText').textContent = winnerText;
        document.getElementById('finalScoreText').textContent = `最终比分：蜀国 ${this.shuScore} : ${this.wuScore} 吴国`;
    },

    // 显示规则模态框
    showRulesModal() {
        document.getElementById('rulesModal').classList.remove('hidden');
    },

    // 隐藏规则模态框
    hideRulesModal() {
        document.getElementById('rulesModal').classList.add('hidden');
    },

    // 重新开始游戏
    restartGame() {
        // 重置游戏状态
        this.state = GAME_STATE.CAMP_SELECT;
        this.playerCamp = null;
        this.aiCamp = null;
        this.currentRound = 1;
        this.currentBattle = 1;
        this.shuScore = 0;
        this.wuScore = 0;
        this.shuRoundScore = 0;
        this.wuRoundScore = 0;
        this.playerDeck = [];
        this.playerHand = [];
        this.aiDeck = [];
        this.aiHand = [];
        this.playerSelectedCards = [];
        this.aiSelectedCards = [];
        this.playerDuelCard = null;
        this.aiDuelCard = null;
        this.attacker = null;
        this.defender = null;
        this.playerBattleCards = [];
        this.aiBattleCards = [];
        this.diceResult = null;
        this.usedCards = [];
        
        // 隐藏规则模态框
        this.hideRulesModal();
        
        // 渲染阵营选择界面
        this.renderCampSelectScreen();
    },
    
    // 创建卡牌背面元素
    createCardBackElement(camp) {
        const cardBackEl = document.createElement('div');
        
        if (camp === CAMPS.WU) {
            // 吴国卡牌背面 - 使用提供的图案风格
            cardBackEl.className = 'w-24 h-32 rounded-lg mr-2 card-shadow overflow-hidden';
            cardBackEl.innerHTML = `
                <div class="w-full h-full bg-blue-500 flex flex-col items-center justify-center relative overflow-hidden">
                    <!-- 亭子装饰 -->
                    <div class="absolute top-2 left-0 right-0 h-12 opacity-30 flex justify-center">
                        <div class="w-20 h-10">
                            <div class="w-full h-2 bg-blue-200 rounded-t"></div>
                            <div class="flex justify-between">
                                <div class="w-2 h-8 bg-blue-300"></div>
                                <div class="w-2 h-8 bg-blue-300"></div>
                                <div class="w-2 h-8 bg-blue-300"></div>
                                <div class="w-2 h-8 bg-blue-300"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 中心吴字 -->
                    <div class="text-5xl font-bold text-yellow-300">吴</div>
                    
                    <!-- 底部装饰 -->
                    <div class="absolute bottom-3 w-20 h-6 flex justify-center">
                        <div class="w-16 h-2 bg-blue-300 rounded-full"></div>
                        <div class="absolute bottom-0 w-10 h-3 bg-yellow-200/50 rounded-full"></div>
                    </div>
                    
                    <!-- 荷花装饰 -->
                    <div class="absolute bottom-1 left-2 w-6 h-5 opacity-70">
                        <div class="w-full h-3 bg-pink-400 rounded-t-full"></div>
                        <div class="w-0.5 h-4 bg-green-500 mx-auto"></div>
                    </div>
                    <div class="absolute bottom-1 right-2 w-6 h-5 opacity-70">
                        <div class="w-full h-3 bg-pink-400 rounded-t-full"></div>
                        <div class="w-0.5 h-4 bg-green-500 mx-auto"></div>
                    </div>
                </div>
            `;
        } else {
            // 蜀国卡牌背面
            cardBackEl.className = 'w-24 h-32 bg-red-700 rounded-lg mr-2 flex items-center justify-center text-white font-bold card-shadow';
            cardBackEl.innerHTML = '<div class="text-3xl">蜀</div>';
        }
        
        return cardBackEl;
    }
};

// 游戏初始化
window.addEventListener('DOMContentLoaded', () => {
    game.init();
});