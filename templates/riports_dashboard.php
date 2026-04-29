<?php
global $wpdb;

if(!current_user_can(VESPA_Roles::riportalas)){
    echo 'Nincs megfelelő jogosultságod az oldal megtekintéséhez.';
    exit;
}

$riportTypes = [
    '' => 'Válassz riport típust',
    'szezon_riport' => 'Szezon riport',
    'verseny_versenyszam' => 'Verseny és versenyszámok statisztika időszakra',
    'verseny_diak' => 'Verseny és diákok statisztika időszakra',
    'legnepszerubb_sportag' => 'Adott időszakra a sportágak népszerűsége',
    'iskola_sportoltatott_diakok' => 'Adott időszakban versenyen indult diákok száma iskolánként',
    'tanev_diakolimpia_diakok' => 'Adott tanévben FODISZ diákolimpián részt vett fogyatékkal élő diákok száma',
    'tanev_versenyen_indult_iskolak' => 'Adott tanévben versenyen indult iskolák száma',
    'tanev_diakolimpia_versenyszam' => 'Adott tanévben Fodisz diákolimpián versenyek száma',
    'tanev_diakolimpia_versenyszam_sportag' => 'Adott tanévben sportágankénti versenyek száma',
];

$gender = [
    'összes',
    'férfi',
    'nő'
];
 
$megyek = $wpdb->get_results("SELECT * FROM vespa_states");
$tk = $wpdb->get_results("SELECT * FROM vespa_school_districts");
$int = $wpdb->get_results("SELECT * FROM vespa_institutions");
$dis = $wpdb->get_results("SELECT * FROM vespa_disability_groups");
$series = $wpdb->get_results("SELECT * FROM vespa_series");
$sports = $wpdb->get_results("SELECT * FROM vespa_sports WHERE is_deleted=0");
?>

<div id="app">
    <div class="wrap">
        <div class="row">
            <div class="col-md-8">
                <h1 class="site-title">Riportok</h1>
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>Riport típusa</label>
                <select name="riportTypes" id="riportTypes" class="form-control input-sm" v-model="selectedRiportType">
                    <?php foreach ($riportTypes as $key => $value) : ?>
                        <option value="<?php echo $key ?>" <?php echo ($record != null && $record->result_type == $key  ? 'selected' : ''); ?>>
                            <?php echo $value; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-4" v-if="showedInputs.filter">
            <div class="form-group">
                <label>Szűrés</label>
                <select name="filter" id="filter" class="form-control input-sm" v-model="selectedRiportData.filter">
                    <option value="all">Összes</option>
                    <option value="country">Csak országos versenyek</option>
                    <optgroup label="Megyei">
                        <option v-for="item in getStateList" :value="item.state_id">
                            {{item.state_name}}
                        </option>
                    </optgroup>       
                </select>
            </div>
        </div>
        <div class="col-md-4" v-if="showedInputs.schoolDistrict">
            <div class="form-group">
                <label>Tankerület választás</label>
                <select name="schoolDistrict" id="schoolDistrict" class="form-control input-sm" v-model="selectedRiportData.schoolDistrict">
                        <option v-for="item in getSchoolDistrictList" :value="item.school_district_id">
                            {{item.school_district_name}}
                        </option>
                </select>
            </div>
        </div>
        <div class="col-md-4" v-if="showedInputs.institution">
            <div class="form-group">
                <label>Intézmény</label>
                <select name="institution" id="institution" class="form-control input-sm" v-model="selectedRiportData.institutionId">
                        <option v-for="item in getInstitutionList" :value="item.institution_id">
                            {{item.ins_name}}
                        </option>
                </select>
            </div>
        </div>
        <div class="col-md-4" v-if="showedInputs.disabilityGroup">
            <div class="form-group">
                <label>Fogyatékossági csoport</label>
                <select name="disabilitie" id="disabilities" class="form-control input-sm" v-model="selectedRiportData.disabilityGroupId">
                        <option v-for="item in getDisabilityList" :value="item.disability_group_id">
                            {{item.disability_group_name}}
                        </option>
                </select>
            </div>
        </div>
        <div class="col-md-4" v-if="showedInputs.gender">
            <div class="form-group">
                <label>Nem</label>
                <select name="gender" id="gender" class="form-control input-sm" v-model="selectedRiportData.gender">
                        <option v-for="item in getGenderList" :value="item">
                            {{item}}
                        </option>
                </select>
            </div>
        </div>
        <div class="col-md-4" v-if="showedInputs.series">
            <div class="form-group">
                <label>Versenysorozat</label>
                <select name="series" id="series" class="form-control input-sm" v-model="selectedRiportData.series">
                        <option v-for="item in getSeriesList" :value="item.series_id">
                            {{item.series_name}}
                        </option>
                </select>
            </div>
        </div>
        <div class="col-md-4" v-if="showedInputs.year">
            <div class="form-group">
                <label>Naptári év</label>
                <select name="year" id="year" class="form-control input-sm" v-model="selectedRiportData.year">
                        <option v-for="item in getYearList" :value="item">
                            {{item === 0 ? 'Összes' : item}}
                        </option>
                </select>
            </div>
        </div>
        <div class="col-md-4" v-if="showedInputs.sport">
            <div class="form-group">
                <label>Sport</label>
                <select name="sport" id="sport" class="form-control input-sm" v-model="selectedRiportData.sport">
                        <option v-for="item in getSportList" :value="item.sport_id">
                            {{item.sport_name}}
                        </option>
                </select>
            </div>
        </div>
        <div class="col-md-4" v-if="showedInputs.interval">
            <div class="form-group">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Dátumtól</label>
                            <input type="date" class="form-control input-sm" name="from" id="from" autocomplete="off" v-model="selectedRiportData.dateFrom">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Dátumig</label>
                            <input type="date" class="form-control input-sm" name="to" id="to" autocomplete="off" v-model="selectedRiportData.dateTo">
                        </div>
                    </div>
            </div>
        </div>

        <div class="col-md-12">
            <button v-if="selectedRiportType" type="button" class="btn btn-primary" @click="submit">Riport generálás</button>
        </div>
    </div>
</div>

<!-- <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script> -->
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script>
    const {
        createApp,
        ref,
    } = Vue

    createApp({
        setup() {
            const riports = ref(<?php echo json_encode($riportTypes) ?>)
            const states = ref(<?php echo json_encode($megyek) ?>)
            const schoolDistricts = ref(<?php echo json_encode($tk) ?>)
            const institutions = ref(<?php echo json_encode($int) ?>)
            const disabilities = ref(<?php echo json_encode($dis) ?>)
            const selectedRiportType = ref('')
            const showedInputs = ref({...this.defaultShowState})
            const selectedRiportData = ref({})
            return {
                riports,
                selectedRiportType,
                selectedRiportData,
                states,
                schoolDistricts,
                institutions,
                disabilities,
                showedInputs
            }
        },
        mounted() {
            this.defaultRiportState.series = this.series[this.series.length - 1].series_id
            this.selectedRiportData = {...this.defaultRiportState}
        },
        data(){
            return {
                defaultShowState: {filter: 0, state: 0, schoolDistrict: 0, institution: 0, disabilityGroup: 0, gender: 0, interval: 0, series: 0, sport: 0, year: 0},
                defaultRiportState: {
                    filter: 'all',
                    schoolDistrict: 0, 
                    institutionId: 0, 
                    disabilityGroupId: 0,
                    series: 0,
                    dateFrom: new Date( new Date().getFullYear(), 0, 2).toISOString().substr(0, 10),
                    dateTo: new Date( new Date().getFullYear(), 11, 32).toISOString().substr(0, 10),
                    gender: 'összes',
                    sport: 0,
                    year: 0
                },
                series: <?php echo json_encode($series) ?>,
                sports: <?php echo json_encode($sports) ?>,
                baseUrl: "<?php echo home_url('/'); ?>",
                gender: <?php echo json_encode($gender); ?>
            }
        },
        methods: {
          submit() {
            window.open(this.getSubmitUri, "_blank") 
          }
        },
        computed: {
            getSubmitUri(){
                const baseUrl = `${this.baseUrl}?download_riports=${this.selectedRiportType}&filter=${this.selectedRiportData.filter}`
                switch (this.selectedRiportType) {
                    case 'verseny_versenyszam':
                    case 'legnepszerubb_sportag':
                        return `${baseUrl}&dateFrom=${this.selectedRiportData.dateFrom}&dateTo=${this.selectedRiportData.dateTo}`          
                    case 'verseny_diak':
                    case 'iskola_sportoltatott_diakok':
                        return `${baseUrl}&dateFrom=${this.selectedRiportData.dateFrom}&dateTo=${this.selectedRiportData.dateTo}&schoolDistrict=${this.selectedRiportData.schoolDistrict}&institutionId=${this.selectedRiportData.institutionId}&disabilityGroupId=${this.selectedRiportData.disabilityGroupId}&gender=${this.selectedRiportData.gender}`
                    case 'tanev_diakolimpia_diakok':
                        return `${baseUrl}&series=${this.selectedRiportData.series}&schoolDistrict=${this.selectedRiportData.schoolDistrict}&institutionId=${this.selectedRiportData.institutionId}&disabilityGroupId=${this.selectedRiportData.disabilityGroupId}&gender=${this.selectedRiportData.gender}`        
                    case 'szezon_riport':
                        return `${baseUrl}&series=${this.selectedRiportData.series}&year=${this.selectedRiportData.year}&schoolDistrict=${this.selectedRiportData.schoolDistrict}&institutionId=${this.selectedRiportData.institutionId}&disabilityGroupId=${this.selectedRiportData.disabilityGroupId}&gender=${this.selectedRiportData.gender}`
                    case 'tanev_diakolimpia_versenyszam':
                        return `${baseUrl}&series=${this.selectedRiportData.series}`;
                    case 'tanev_diakolimpia_versenyszam_sportag':
                        return `${baseUrl}&series=${this.selectedRiportData.series}`;
                    case 'tanev_versenyen_indult_iskolak':
                        return `${baseUrl}&series=${this.selectedRiportData.series}&schoolDistrict=${this.selectedRiportData.schoolDistrict}`
                    default:
                        break;
                }               
            },
            getStateList() {
                return [{state_id: 0, state_name: 'Összes megye'}, ...this.states]
            },
            getSchoolDistrictList() {
                return [{school_district_id: 0, school_district_name: 'Összes tankerület'}, ...this.schoolDistricts]
            },
            getInstitutionList() {
                return [{institution_id: 0, ins_name: 'Összes intézmény'}, ...this.institutions]
            },
            getDisabilityList() {
                return [{disability_group_id: 0, disability_group_name: 'Összes'}, ...this.disabilities]
            },
            getSeriesList() {
                return [...this.series]
            },
            getGenderList() {
                return [...this.gender]
            },
            getSportList() {
                return [{sport_id: 0, sport_name: 'Összes'}, ...this.sports]
            },
            getYearList() {
                const currentYear = new Date().getFullYear()
                const years = [0]
                for (let y = 2023; y <= currentYear + 1; y++) {
                    years.push(y)
                }
                return years
            },
        },
        watch: {
            selectedRiportType(newType) {
                this.selectedRiportData = {...this.defaultRiportState}
                switch (newType) {
                    case 'verseny_versenyszam':
                        this.showedInputs = {...this.defaultShowState, filter: 1, interval: 1}
                        break;
                    case 'verseny_diak':
                        this.showedInputs = {...this.defaultShowState, filter: 1, interval: 1, gender: 1, disabilityGroup: 1, schoolDistrict: 1, institution: 1}
                        break;
                    case 'legnepszerubb_sportag':
                        this.showedInputs = {...this.defaultShowState, filter: 1, interval: 1, gender: 1, disabilityGroup: 1, schoolDistrict: 1, institution: 1, sport: 1}
                        break;
                    case 'iskola_sportoltatott_diakok':
                        this.showedInputs = {...this.defaultShowState, filter: 1, interval: 1, gender: 1, disabilityGroup: 1, schoolDistrict: 1, institution: 1, sport: 1}
                        break;
                    case 'tanev_diakolimpia_diakok':
                        this.showedInputs = {...this.defaultShowState, filter: 1, series: 1, gender: 1, disabilityGroup: 1, schoolDistrict: 1, institution: 1, sport: 1}
                        break;
                    case 'szezon_riport':
                        this.showedInputs = {...this.defaultShowState, filter: 1, series: 1, year: 1, gender: 1, disabilityGroup: 1, schoolDistrict: 1, institution: 1}
                        break;
                    case 'tanev_versenyen_indult_iskolak':
                        this.showedInputs = {...this.defaultShowState, filter: 1, series: 1, schoolDistrict: 1}
                        break;
                    case 'tanev_diakolimpia_versenyszam':
                        this.showedInputs = {...this.defaultShowState, filter: 1, series: 1}
                        break;
                    case 'tanev_diakolimpia_versenyszam_sportag':
                        this.showedInputs = {...this.defaultShowState, filter: 1, series: 1}
                        break;
                    default:
                        break;
                }
            }
        },
    }).mount('#app')
    
</script>